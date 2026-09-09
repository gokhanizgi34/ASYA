<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Str;

class SeoContentOptimizer
{
    public function __construct(private readonly ArticleBodyFormatter $bodyFormatter) {}

    public function optimize(Article $article, ?string $focusKeyword = null): Article
    {
        $body = $this->bodyFormatter->normalizeMarkdown($article->body);
        $plainBody = Str::of(strip_tags($body))->replaceMatches('/\s+/u', ' ')->squish()->toString();
        $summarySource = Str::of(trim(strip_tags((string) $article->summary)).' '.$plainBody)
            ->replaceMatches('/\s+/u', ' ')
            ->squish()
            ->toString();
        $summary = Str::limit($summarySource, 155, '');

        $headingKeyword = $focusKeyword ?: $article->title;
        if (! $this->hasHeading($body) || ! $this->headingContainsKeyword($body, $headingKeyword)) {
            $body = $this->addHeading($body, $headingKeyword);
        }

        $body = $this->bodyFormatter->normalizeMarkdown($body);

        $article->forceFill([
            'summary' => $summary,
            'body' => $body,
        ])->save();

        return $article->refresh();
    }

    private function hasHeading(string $body): bool
    {
        return preg_match('/(?:^|\R)#{2,4}\s+|<h[2-4]\b/iu', $body) === 1;
    }

    private function headingContainsKeyword(string $body, string $focusKeyword): bool
    {
        $focusKeyword = Str::of(strip_tags($focusKeyword))->lower()->squish()->toString();

        if ($focusKeyword === '') {
            return true;
        }

        preg_match_all('/(?:^|\R)#{2,4}\s+([^\r\n]+)|<h[2-4][^>]*>(.*?)<\/h[2-4]>/iu', $body, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $heading = Str::of(strip_tags((string) ($match[1] ?: ($match[2] ?? ''))))->lower()->squish()->toString();
            if (Str::contains($heading, $focusKeyword)) {
                return true;
            }
        }

        return false;
    }

    private function addHeading(string $body, string $focusKeyword): string
    {
        $paragraphs = preg_split('/\R{2,}/u', trim($body), -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($paragraphs) || count($paragraphs) < 2) {
            return $body;
        }

        $headingKeyword = Str::of(strip_tags($focusKeyword))
            ->replaceMatches('/\s+/u', ' ')
            ->squish()
            ->words(6, '')
            ->ucfirst()
            ->toString();
        $heading = '## '.($headingKeyword !== '' ? $headingKeyword.' Hakkında Ayrıntılar' : 'Gelişmenin Ayrıntıları');
        $position = count($paragraphs) >= 4 ? 2 : 1;
        array_splice($paragraphs, $position, 0, [$heading]);

        return implode("\n\n", $paragraphs);
    }
}
