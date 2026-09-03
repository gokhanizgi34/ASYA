<?php

namespace App\Services;

use App\Models\RawNewsItem;
use Illuminate\Support\Str;

class NewsDuplicateDetector
{
    public function exists(int $agencyId, string $title, ?int $exceptId = null): bool
    {
        $candidate = $this->tokens($title);

        if (count($candidate) < 3) {
            return false;
        }

        return RawNewsItem::query()
            ->where('agency_id', $agencyId)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->latest('id')
            ->limit(1000)
            ->get(['id', 'original_title'])
            ->contains(fn (RawNewsItem $item): bool => $this->similar($candidate, $this->tokens($item->original_title)));
    }

    /** @param array<int, string> $left @param array<int, string> $right */
    public function titlesAreSimilar(string $left, string $right): bool
    {
        return $this->similar($this->tokens($left), $this->tokens($right));
    }

    private function similar(array $left, array $right): bool
    {
        if (count($right) < 3) {
            return false;
        }

        $intersection = count(array_intersect($left, $right));
        $union = count(array_unique([...$left, ...$right]));
        $jaccard = $union === 0 ? 0 : $intersection / $union;
        $containment = $intersection / min(count($left), count($right));
        similar_text(implode(' ', $left), implode(' ', $right), $textSimilarity);

        return $jaccard >= 0.72
            || ($intersection >= 4 && $containment >= 0.66)
            || $textSimilarity >= 86;
    }

    /** @return array<int, string> */
    private function tokens(string $title): array
    {
        $stopWords = ['bir', 'ile', 'icin', 've', 'ya', 'da', 'de', 'ta', 'te', 'the', 'son', 'dakika'];

        return collect(preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($title))) ?: [])
            ->filter(fn (string $token): bool => strlen($token) >= 2 && ! in_array($token, $stopWords, true))
            ->map(function (string $token): string {
                if (preg_match('/^(.{5,})(?:de|da|te|ta)$/', $token, $matches) === 1) {
                    return $matches[1];
                }

                return $token;
            })
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
