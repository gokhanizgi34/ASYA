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
        $plainBody = trim(strip_tags($article->body));
        $plainSummary = trim(strip_tags((string) $article->summary));
        $words = $this->words($plainBody);
        $wordCount = count($words);
        $keywords = $this->extractKeywords($article->title.' '.$plainBody);
        $focusKeyword = trim((string) $requestedFocusKeyword) ?: ($keywords[0] ?? Str::lower($article->title));
        $metaTitle = Str::limit(trim($article->title), 60, '');
        $metaDescription = Str::limit($plainSummary ?: $plainBody, 155, '');
        $keywordDensity = $this->keywordDensity($plainBody, $focusKeyword, $wordCount);
        $readabilityScore = $this->readabilityScore($plainBody, $wordCount);
        [$score, $issues, $recommendations] = $this->score(
            $article->title,
            $metaDescription,
            $plainBody,
            $focusKeyword,
            $wordCount,
            $keywordDensity,
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

    /**
     * @return array<int, string>
     */
    private function words(string $text): array
    {
        $normalized = Str::lower(strip_tags($text));
        $words = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? $words : [];
    }

    /**
     * @return array<int, string>
     */
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

    private function keywordDensity(string $body, string $focusKeyword, int $wordCount): float
    {
        if ($wordCount === 0 || $focusKeyword === '') {
            return 0.0;
        }

        $normalizedBody = Str::lower($body);
        $normalizedKeyword = Str::lower($focusKeyword);
        $occurrences = substr_count($normalizedBody, $normalizedKeyword);
        $keywordWords = max(1, count($this->words($normalizedKeyword)));

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

    /**
     * @return array{int, array<int, string>, array<int, string>}
     */
    private function score(string $title, string $description, string $body, string $focusKeyword, int $wordCount, float $density, int $readability): array
    {
        $score = 100;
        $issues = [];
        $recommendations = [];
        $titleLength = mb_strlen($title);
        $descriptionLength = mb_strlen($description);

        if ($titleLength < 30 || $titleLength > 65) {
            $score -= 15;
            $issues[] = 'Başlık uzunluğu SEO aralığının dışında.';
            $recommendations[] = 'Başlığı 30-65 karakter aralığında tutun.';
        }

        if ($descriptionLength < 120 || $descriptionLength > 160) {
            $score -= 15;
            $issues[] = 'Meta açıklama uzunluğu uygun değil.';
            $recommendations[] = 'Meta açıklamayı 120-160 karakter aralığında hazırlayın.';
        }

        if ($wordCount < 300) {
            $score -= 20;
            $issues[] = 'Haber metni kısa.';
            $recommendations[] = 'İçeriği en az 300 kelimeye tamamlayın.';
        }

        if (! Str::contains(Str::lower($title), Str::lower($focusKeyword))) {
            $score -= 15;
            $issues[] = 'Odak anahtar kelime başlıkta bulunmuyor.';
            $recommendations[] = 'Odak anahtar kelimeyi doğal biçimde başlığa ekleyin.';
        }

        if ($density < 0.5 || $density > 3.0) {
            $score -= 15;
            $issues[] = 'Anahtar kelime yoğunluğu önerilen aralığın dışında.';
            $recommendations[] = 'Anahtar kelime yoğunluğunu %0,5-%3 aralığına getirin.';
        }

        if ($readability < 60) {
            $score -= 10;
            $issues[] = 'Cümleler ortalama olarak çok uzun.';
            $recommendations[] = 'Uzun cümleleri bölerek okunabilirliği artırın.';
        }

        if (! preg_match('/(?:^|\R)#{1,3}\s+/u', $body)) {
            $score -= 10;
            $issues[] = 'Metinde ara başlık bulunmuyor.';
            $recommendations[] = 'Metni H2/H3 niteliğinde ara başlıklarla bölün.';
        }

        return [max(0, $score), $issues, array_values(array_unique($recommendations))];
    }
}
