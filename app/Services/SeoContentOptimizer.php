<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Str;

class SeoContentOptimizer
{
    public function optimize(Article $article, ?string $focusKeyword = null): Article
    {
        $body = trim($article->body);
        $plainBody = Str::of(strip_tags($body))->replaceMatches('/\s+/u', ' ')->squish()->toString();
        $summarySource = Str::of(trim(strip_tags((string) $article->summary)).' '.$plainBody)
            ->replaceMatches('/\s+/u', ' ')
            ->squish()
            ->toString();
        $summary = Str::limit($summarySource, 155, '');

        if (! $this->hasHeading($body)) {
            $body = $this->addHeading($body, $focusKeyword ?: $article->title);
        }

        $article->forceFill([
            'summary' => $summary,
            'body' => $body,
        ])->save();

        return $article->refresh();
    }

    private function hasHeading(string $body): bool
    {
        return preg_match('/(?:^|\R)#{2,3}\s+|<h[23]\b/iu', $body) === 1;
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
