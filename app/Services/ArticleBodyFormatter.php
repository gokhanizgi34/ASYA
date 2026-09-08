<?php

namespace App\Services;

use Illuminate\Support\Str;

class ArticleBodyFormatter
{
    public function normalizeMarkdown(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        $body = preg_replace('/[ \t]+\n/u', "\n", $body) ?? $body;
        $blocks = preg_split('/\n{2,}/u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $currentHeadingLevel = 1;

        return collect($blocks)
            ->map(function (string $block) use (&$currentHeadingLevel): string {
                $block = trim($block);

                if (preg_match('/^(#{1,6})\s+(.+)$/us', $block, $matches) !== 1) {
                    return $block;
                }

                $requestedLevel = max(2, min(4, mb_strlen($matches[1])));
                $headingLevel = min($requestedLevel, $currentHeadingLevel + 1);
                $currentHeadingLevel = $headingLevel;
                $heading = Str::of(strip_tags($matches[2]))
                    ->replaceMatches('/\s+/u', ' ')
                    ->squish()
                    ->trim(" \t\n\r\0\x0B#")
                    ->toString();

                return $heading === '' ? '' : str_repeat('#', $headingLevel).' '.$heading;
            })
            ->filter(fn (string $block): bool => $block !== '')
            ->implode("\n\n");
    }

    public function toHtml(string $body): string
    {
        $body = $this->normalizeMarkdown($body);
        $blocks = preg_split('/\n{2,}/u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($blocks)
            ->map(function (string $block): string {
                $block = trim($block);

                if (preg_match('/^(#{2,4})\s+(.+)$/us', $block, $matches) === 1) {
                    $level = mb_strlen($matches[1]);
                    $margins = match ($level) {
                        2 => '2rem 0 0.875rem',
                        3 => '1.625rem 0 0.75rem',
                        default => '1.375rem 0 0.625rem',
                    };

                    return '<h'.$level.' style="margin:'.$margins.';line-height:1.35">'.e(trim($matches[2])).'</h'.$level.'>';
                }

                return '<p style="margin:0 0 1.125rem;line-height:1.75">'.nl2br(e($block), false).'</p>';
            })
            ->implode("\n");
    }
}
