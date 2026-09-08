<?php

namespace App\Services;

use Illuminate\Support\Str;

class ArticleBodyFormatter
{
    public function normalizeMarkdown(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        $body = preg_replace('/[ \t]+\n/u', "\n", $body) ?? $body;
        $body = $this->separateInlineHeadings($body);
        $blocks = preg_split('/\n{2,}/u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $currentHeadingLevel = 1;

        return collect($blocks)
            ->map(function (string $block) use (&$currentHeadingLevel): string {
                $block = trim($block);

                if (preg_match('/^(#{1,6})\s+(.+)$/us', $block, $matches) !== 1) {
                    return $this->normalizeParagraphBlock($block);
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

    private function separateInlineHeadings(string $body): string
    {
        return preg_replace_callback(
            '/(?:^|\s)(#{2,4})[ \t]+(.+?)(?=(?:\s+#{2,4}[ \t]+)|$)/us',
            function (array $matches): string {
                [$heading, $content] = $this->splitInlineHeadingSection(trim($matches[2]));
                $section = "\n\n".$matches[1].' '.$heading;

                return $content === '' ? $section : $section."\n\n".$content;
            },
            $body,
        ) ?? $body;
    }

    /** @return array{string, string} */
    private function splitInlineHeadingSection(string $section): array
    {
        if (preg_match('/^([^\n]+)\n+(.*)$/us', $section, $matches) === 1) {
            return [trim($matches[1]), trim($matches[2])];
        }

        $words = preg_split('/\s+/u', $section, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= 8) {
            return [$section, ''];
        }

        $maximumHeadingWords = min(8, count($words) - 1);
        for ($index = 3; $index <= $maximumHeadingWords; $index++) {
            if (preg_match('/[:!?]$/u', $words[$index - 1]) === 1) {
                return [implode(' ', array_slice($words, 0, $index)), implode(' ', array_slice($words, $index))];
            }

            $startsSentence = preg_match('/^\p{Lu}/u', $words[$index]) === 1
                && isset($words[$index + 1])
                && preg_match('/^\p{Ll}/u', $words[$index + 1]) === 1;

            if ($startsSentence) {
                return [implode(' ', array_slice($words, 0, $index)), implode(' ', array_slice($words, $index))];
            }
        }

        return [implode(' ', array_slice($words, 0, $maximumHeadingWords)), implode(' ', array_slice($words, $maximumHeadingWords))];
    }

    private function normalizeParagraphBlock(string $block): string
    {
        if (preg_match('/(?:^|\n)\s*(?:[-*]|\d+[.)])\s+/u', $block) === 1) {
            return $block;
        }

        $sentences = preg_split('/(?<=[.!?])\s+(?=\p{Lu})/u', Str::squish($block), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($sentences) <= 4) {
            return implode(' ', $sentences);
        }

        return collect(array_chunk($sentences, 3))
            ->map(fn (array $sentenceGroup): string => implode(' ', $sentenceGroup))
            ->implode("\n\n");
    }
}
