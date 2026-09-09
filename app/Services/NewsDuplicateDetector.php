<?php

namespace App\Services;

use App\Models\RawNewsItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class NewsDuplicateDetector
{
    public function exists(
        int $agencyId,
        string $title,
        ?int $exceptId = null,
        CarbonInterface|string|null $occurredAt = null,
    ): bool {
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
            ->get(['id', 'original_title', 'discovered_at', 'created_at'])
            ->contains(fn (RawNewsItem $item): bool => $this->similar($candidate, $this->tokens($item->original_title))
                || $this->isRecentEventMatch(
                    $candidate,
                    $this->tokens($item->original_title),
                    $occurredAt,
                    $item->discovered_at ?? $item->created_at,
                ));
    }

    public function withinAgencyLock(int $agencyId, string $scope, Closure $callback): mixed
    {
        return Cache::lock('news-duplicate:'.$scope.':agency-'.$agencyId, 300)
            ->block(30, $callback);
    }

    public function titlesAreSimilar(string $left, string $right): bool
    {
        return $this->similar($this->tokens($left), $this->tokens($right));
    }

    public function reportsSameEvent(
        string $leftTitle,
        string $rightTitle,
        CarbonInterface|string|null $leftOccurredAt = null,
        CarbonInterface|string|null $rightOccurredAt = null,
    ): bool {
        $left = $this->tokens($leftTitle);
        $right = $this->tokens($rightTitle);

        return $this->similar($left, $right)
            || $this->isRecentEventMatch($left, $right, $leftOccurredAt, $rightOccurredAt);
    }

    /**
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     */
    private function similar(array $left, array $right): bool
    {
        if (count($left) < 3 || count($right) < 3) {
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

    /**
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     */
    private function isRecentEventMatch(
        array $left,
        array $right,
        CarbonInterface|string|null $leftOccurredAt,
        CarbonInterface|string|null $rightOccurredAt,
    ): bool {
        $leftTime = $this->date($leftOccurredAt);
        $rightTime = $this->date($rightOccurredAt);

        if (! $leftTime || ! $rightTime || $leftTime->diffInHours($rightTime, true) > 6) {
            return false;
        }

        $shared = $this->sharedTokens($left, $right);
        $shorterCount = min(count($left), count($right));
        $sharedCharacterCount = array_sum(array_map('strlen', $shared));

        return (count($shared) >= 3
                && $shorterCount > 0
                && count($shared) / $shorterCount >= 0.6
                && $sharedCharacterCount >= 20)
            || (count($shared) >= 4 && $sharedCharacterCount >= 28);
    }

    /**
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     * @return array<int, string>
     */
    private function sharedTokens(array $left, array $right): array
    {
        return collect($left)
            ->filter(fn (string $leftToken): bool => collect($right)->contains(
                fn (string $rightToken): bool => $leftToken === $rightToken
                    || (min(strlen($leftToken), strlen($rightToken)) >= 6
                        && abs(strlen($leftToken) - strlen($rightToken)) <= 3
                        && (str_starts_with($leftToken, $rightToken) || str_starts_with($rightToken, $leftToken))),
            ))
            ->values()
            ->all();
    }

    private function date(CarbonInterface|string|null $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<int, string> */
    private function tokens(string $title): array
    {
        $stopWords = ['bir', 'ile', 'icin', 've', 'ya', 'da', 'de', 'ta', 'te', 'the', 'son', 'dakika', 'haber', 'haberi', 'haberler', 'baslik', 'basligi', 'belediye', 'belediyesi'];

        return collect(preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($title))) ?: [])
            ->filter(fn (string $token): bool => strlen($token) >= 2 && ! in_array($token, $stopWords, true))
            ->map(fn (string $token): string => $this->stemTurkishToken($token))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function stemTurkishToken(string $token): string
    {
        foreach (['ndaki', 'ndeki', 'daki', 'deki', 'taki', 'teki', 'inin', 'unun', 'nin', 'nun', 'inda', 'inde', 'unda', 'unde', 'dan', 'den', 'tan', 'ten', 'lar', 'ler', 'da', 'de', 'ta', 'te', 'in', 'un'] as $suffix) {
            if (strlen($token) - strlen($suffix) >= 5 && str_ends_with($token, $suffix)) {
                return substr($token, 0, -strlen($suffix));
            }
        }

        if (strlen($token) >= 7 && preg_match('/^(.+ti)(?:gi|gu)$/', $token, $matches) === 1) {
            return $matches[1];
        }

        return $token;
    }
}
