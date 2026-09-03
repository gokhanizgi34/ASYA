<?php

namespace App\Services;

use App\BlacklistAction;
use App\BlacklistRuleType;
use App\Models\BlacklistRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BlacklistMatcher
{
    /**
     * @param  array{title?: string|null, body?: string|null, source_name?: string|null, source_url?: string|null}  $content
     * @return array{blocked: bool, requires_review: bool, matches: Collection<int, BlacklistRule>}
     */
    public function evaluate(int $agencyId, array $content, bool $recordHits = true): array
    {
        $haystack = $this->normalizeText(implode(' ', array_filter([$content['title'] ?? null, $content['body'] ?? null])));
        $sourceName = $this->normalizeText((string) ($content['source_name'] ?? ''));
        $sourceUrl = trim((string) ($content['source_url'] ?? ''));
        $host = Str::lower((string) parse_url($sourceUrl, PHP_URL_HOST));

        $matches = BlacklistRule::query()
            ->where('agency_id', $agencyId)
            ->where('is_active', true)
            ->get()
            ->filter(function (BlacklistRule $rule) use ($haystack, $host, $sourceName, $sourceUrl): bool {
                return match ($rule->type) {
                    BlacklistRuleType::Keyword => Str::contains($haystack, $rule->normalized_pattern),
                    BlacklistRuleType::Domain => $host === $rule->normalized_pattern || Str::endsWith($host, '.'.$rule->normalized_pattern),
                    BlacklistRuleType::UrlPrefix => Str::startsWith(Str::lower($sourceUrl), $rule->normalized_pattern),
                    BlacklistRuleType::Source => $sourceName === $rule->normalized_pattern,
                };
            })
            ->values();

        if ($recordHits && $matches->isNotEmpty()) {
            BlacklistRule::query()->whereKey($matches->modelKeys())->increment('hit_count');
            BlacklistRule::query()->whereKey($matches->modelKeys())->update(['last_hit_at' => now()]);
        }

        return [
            'blocked' => $matches->contains('action', BlacklistAction::Block),
            'requires_review' => $matches->contains('action', BlacklistAction::Review),
            'matches' => $matches,
        ];
    }

    public function normalize(BlacklistRuleType $type, string $pattern): string
    {
        $value = trim($pattern);

        return match ($type) {
            BlacklistRuleType::Keyword, BlacklistRuleType::Source => $this->normalizeText($value),
            BlacklistRuleType::Domain => Str::lower((string) (parse_url(preg_match('/^https?:\/\//i', $value) === 1 ? $value : 'https://'.$value, PHP_URL_HOST) ?: $value)),
            BlacklistRuleType::UrlPrefix => Str::lower(rtrim($value, '/')),
        };
    }

    private function normalizeText(string $value): string
    {
        $turkishCaseNormalized = str_replace(['I', 'İ'], ['ı', 'i'], $value);

        return Str::lower(Str::squish(strip_tags($turkishCaseNormalized)));
    }
}
