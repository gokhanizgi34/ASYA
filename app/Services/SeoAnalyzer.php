<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SeoAnalyzer
{
    /** @var array<int, string> */
    private const STOP_WORDS = [
        'acaba', 'ama', 'ancak', 'artık', 'aslında', 'bana', 'bazı', 'belki', 'ben', 'bile', 'bir', 'biz', 'böyle', 'bu', 'bütün', 'çok', 'çünkü', 'daha', 'değil', 'diye', 'en', 'gibi', 'hem', 'hep', 'her', 'için', 'ile', 'ise', 'kadar', 'karşı', 'kendi', 'mi', 'nasıl', 'neden', 'olan', 'olarak', 'oldu', 'olmak', 'önce', 'sadece', 'sonra', 'şey', 'şimdi', 'tüm', 've', 'veya', 'ya', 'yine', 'zaten',
    ];

    /**
     * @return array{focus_keyword: string, meta_title: string, meta_description: string, keywords: array<int, string>, hashtags: array<int, string>, score: int, readability_score: int, word_count: int, keyword_density: float, issues: array<int, string>, recommendations: array<int, string>, analyzed_at: Carbon}
     */
    public function analyze(Article $article, ?string $requestedFocusKeyword = null): array
    {
        $plainBody = Str::of(strip_tags($article->body))->replaceMatches('/\s+/u', ' ')->squish()->toString();
        $plainSummary = Str::of(strip_tags((string) $article->summary))->replaceMatches('/\s+/u', ' ')->squish()->toString();
        $words = $this->words($plainBody);
        $wordCount = count($words);
        $keywords = $this->extractKeywords($article->title.' '.$plainBody);
        $focusKeyword = $this->focusKeyword($article->title, $plainBody, $requestedFocusKeyword, $keywords);
        $metaTitle = $this->metaTitle($article->title, $focusKeyword);
        $descriptionSource = Str::of($plainSummary.' '.$plainBody)->squish()->toString();
        if ($focusKeyword !== '' && ! Str::contains(Str::lower(Str::limit($descriptionSource, 155, '')), Str::lower($focusKeyword))) {
            $descriptionSource = Str::ucfirst($focusKeyword).': '.$descriptionSource;
        }
        if (mb_strlen($descriptionSource) < 120) {
            $descriptionSource = Str::of($descriptionSource.' Gelişmeye ilişkin doğrulanabilen ayrıntılar ve açıklamalar haber metninde aktarılıyor.')
                ->squish()
                ->toString();
        }
        $metaDescription = Str::limit($descriptionSource, 155, '');
        $keywordDensity = $this->keywordDensity($plainBody, $focusKeyword, $wordCount);
        $readabilityScore = $this->readabilityScore($plainBody, $wordCount);
        [$score, $issues, $recommendations] = $this->score(
            $metaTitle,
            $metaDescription,
            $article->body,
            $plainBody,
            $focusKeyword,
            $wordCount,
            $readabilityScore,
        );

        return [
            'focus_keyword' => $focusKeyword,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'keywords' => $keywords,
            'hashtags' => array_map(fn (string $keyword): string => '#'.Str::studly($keyword), array_slice($keywords, 0, 5)),
            'score' => $score,
            'readability_score' => $readabilityScore,
            'word_count' => $wordCount,
            'keyword_density' => $keywordDensity,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'analyzed_at' => now(),
        ];
    }

    /** @return array<int, string> */
    private function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower(strip_tags($text)), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? $words : [];
    }

    /** @return array<int, string> */
    private function extractKeywords(string $text): array
    {
        $frequency = [];

        foreach ($this->words($text) as $word) {
            if (mb_strlen($word) < 4 || in_array($word, self::STOP_WORDS, true)) {
                continue;
            }

            $frequency[$word] = ($frequency[$word] ?? 0) + 1;
        }

        arsort($frequency);

        return array_slice(array_keys($frequency), 0, 8);
    }

    /** @param array<int, string> $keywords */
    private function focusKeyword(string $title, string $body, ?string $requested, array $keywords): string
    {
        $requested = Str::of((string) $requested)->lower()->squish()->toString();
        $titleLower = Str::lower($title);
        $bodyLower = Str::lower($body);

        if ($requested !== '') {
            return $requested;
        }

        foreach ($keywords as $keyword) {
            if (Str::contains($titleLower, $keyword) && Str::contains($bodyLower, $keyword)) {
                return $keyword;
            }
        }

        return $requested ?: ($keywords[0] ?? Str::lower(Str::words($title, 3, '')));
    }

    private function metaTitle(string $title, string $focusKeyword): string
    {
        $title = Str::squish(strip_tags($title));
        $focusKeyword = Str::squish(strip_tags($focusKeyword));

        if ($focusKeyword !== '' && ! Str::startsWith(Str::lower($title), Str::lower($focusKeyword))) {
            $title = Str::ucfirst($focusKeyword).': '.$title;
        }

        $yearSuffix = ' | '.now()->year;
        $title = Str::limit($title, 60 - mb_strlen($yearSuffix), '');

        return $title.$yearSuffix;
    }

    private function keywordDensity(string $body, string $focusKeyword, int $wordCount): float
    {
        if ($wordCount === 0 || $focusKeyword === '') {
            return 0.0;
        }

        $occurrences = substr_count(Str::lower($body), Str::lower($focusKeyword));
        $keywordWords = max(1, count($this->words($focusKeyword)));

        return round(($occurrences * $keywordWords / $wordCount) * 100, 2);
    }

    private function readabilityScore(string $body, int $wordCount): int
    {
        if ($wordCount === 0) {
            return 0;
        }

        $sentences = preg_split('/[.!?]+/u', $body, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = max(1, is_array($sentences) ? count($sentences) : 1);
        $averageWords = $wordCount / $sentenceCount;

        return max(0, min(100, (int) round(100 - max(0, $averageWords - 15) * 3)));
    }

    /** @return array{int, array<int, string>, array<int, string>} */
    private function score(string $metaTitle, string $description, string $body, string $plainBody, string $focusKeyword, int $wordCount, int $readability): array
    {
        $score = 100;
        $issues = [];
        $recommendations = [];

        if (mb_strlen($metaTitle) < 30 || mb_strlen($metaTitle) > 60) {
            $score -= 15;
            $issues[] = 'SEO başlığı uygun uzunlukta değil.';
            $recommendations[] = 'SEO başlığını 30-60 karakter aralığında tutun.';
        }

        if (mb_strlen($description) < 120 || mb_strlen($description) > 160) {
            $score -= 15;
            $issues[] = 'Meta açıklama uzunluğu uygun değil.';
            $recommendations[] = 'Meta açıklamayı 120-160 karakter aralığında hazırlayın.';
        }

        if ($wordCount < 60) {
            $score -= 30;
            $issues[] = 'Haber metni kısa.';
            $recommendations[] = 'Doğrulanabilen ayrıntıları kısa paragraflarla açıklayın.';
        }

        if ($focusKeyword === '' || ! Str::contains(Str::lower($metaTitle), Str::lower($focusKeyword))) {
            $score -= 15;
            $issues[] = 'Odak anahtar kelime SEO başlığında bulunmuyor.';
            $recommendations[] = 'Odak anahtar kelimeyi doğal biçimde SEO başlığına ekleyin.';
        }

        if ($focusKeyword === '' || ! Str::contains(Str::lower($plainBody), Str::lower($focusKeyword))) {
            $score -= 15;
            $issues[] = 'Odak anahtar kelime haber metninde bulunmuyor.';
            $recommendations[] = 'Odak anahtar kelimeyi haber metninde doğal biçimde kullanın.';
        }

        if ($readability < 60) {
            $score -= 15;
            $issues[] = 'Cümleler ortalama olarak çok uzun.';
            $recommendations[] = 'Uzun cümleleri bölerek okunabilirliği artırın.';
        }

        if ($wordCount >= 180 && preg_match('/(?:^|\R)#{2,3}\s+|<h[23]\b/iu', $body) !== 1) {
            $score -= 15;
            $issues[] = 'Uzun metinde ara başlık bulunmuyor.';
            $recommendations[] = 'Uzun metni H2/H3 niteliğinde ara başlıklarla bölün.';
        }

        return [max(0, $score), $issues, array_values(array_unique($recommendations))];
    }
}
