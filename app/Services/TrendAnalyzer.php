<?php

namespace App\Services;

use App\Models\Article;
use App\Models\RawNewsItem;
use App\Models\SeoAnalysis;
use App\Models\TrendSnapshot;
use App\Models\TrendTopic;
use App\TrendStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrendAnalyzer
{
    /** @var array<int, string> */
    private const STOP_WORDS = ['acaba', 'ancak', 'artık', 'bana', 'bazı', 'belki', 'bile', 'biri', 'bunu', 'çok', 'daha', 'değil', 'diye', 'gibi', 'hangi', 'için', 'ile', 'ise', 'kadar', 'kendi', 'olan', 'olarak', 'oldu', 'sonra', 'şimdi', 'tüm', 'üzere', 'veya', 'yeni', 'the', 'and', 'from', 'that', 'this', 'with'];

    /** @return array{topics: int, signals: int} */
    public function analyze(int $agencyId, ?CarbonImmutable $now = null): array
    {
        $analyzedAt = $now ?? CarbonImmutable::now();
        $currentStart = $analyzedAt->subDay();
        $previousStart = $analyzedAt->subDays(2);
        $current = [];
        $previous = [];

        RawNewsItem::query()->where('agency_id', $agencyId)->where(function ($query) use ($previousStart): void {
            $query->where('discovered_at', '>=', $previousStart)->orWhere(fn ($nested) => $nested->whereNull('discovered_at')->where('created_at', '>=', $previousStart));
        })->orderBy('id')->limit(5000)->get(['id', 'source_name', 'original_title', 'discovered_at', 'created_at'])->each(function (RawNewsItem $item) use (&$current, &$previous, $currentStart): void {
            $seenAt = CarbonImmutable::instance($item->discovered_at ?? $item->created_at);
            if ($seenAt->gte($currentStart)) {
                $this->recordTitleSignals($current, $item->original_title, $item->source_name, $seenAt);
            } else {
                $this->recordTitleSignals($previous, $item->original_title, $item->source_name, $seenAt);
            }
        });

        Article::query()->where('agency_id', $agencyId)->where('created_at', '>=', $previousStart)->orderBy('id')->limit(5000)->get(['id', 'source_name', 'title', 'created_at'])->each(function (Article $article) use (&$current, &$previous, $currentStart): void {
            $seenAt = CarbonImmutable::instance($article->created_at);
            if ($seenAt->gte($currentStart)) {
                $this->recordTitleSignals($current, $article->title, $article->source_name ?: 'ASYA', $seenAt);
            } else {
                $this->recordTitleSignals($previous, $article->title, $article->source_name ?: 'ASYA', $seenAt);
            }
        });

        SeoAnalysis::query()->where('agency_id', $agencyId)->where('analyzed_at', '>=', $previousStart)->orderBy('id')->limit(5000)->get(['article_id', 'focus_keyword', 'keywords', 'analyzed_at'])->each(function (SeoAnalysis $analysis) use (&$current, &$previous, $currentStart): void {
            $seenAt = CarbonImmutable::instance($analysis->analyzed_at);
            foreach (array_filter([$analysis->focus_keyword, ...($analysis->keywords ?? [])]) as $keyword) {
                if ($seenAt->gte($currentStart)) {
                    $this->recordSignal($current, (string) $keyword, 'seo:'.$analysis->article_id, $seenAt, 2);
                } else {
                    $this->recordSignal($previous, (string) $keyword, 'seo:'.$analysis->article_id, $seenAt, 2);
                }
            }
        });

        $periodEnd = $analyzedAt->setMinute(intdiv($analyzedAt->minute, 15) * 15)->startOfMinute();
        $periodStart = $periodEnd->subMinutes(15);
        $topicCount = 0;

        foreach ($current as $normalized => $signal) {
            if ($signal['count'] < 2) {
                continue;
            }
            $previousCount = $previous[$normalized]['count'] ?? 0;
            $velocity = round((($signal['count'] - $previousCount) / max(1, $previousCount)) * 100, 2);
            $sourceCount = count($signal['sources']);
            $score = round(($signal['count'] * 10) + ($sourceCount * 5) + min(50, max(-20, $velocity / 10)), 2);
            $status = $this->status($score, $velocity, $sourceCount);

            DB::transaction(function () use ($agencyId, $normalized, $signal, $sourceCount, $score, $velocity, $status, $analyzedAt, $periodStart, $periodEnd): void {
                $topic = TrendTopic::query()->firstOrNew(['agency_id' => $agencyId, 'normalized_name' => $normalized]);
                $topic->fill([
                    'name' => $signal['name'],
                    'status' => $status,
                    'mention_count' => $signal['count'],
                    'source_count' => $sourceCount,
                    'score' => $score,
                    'velocity' => $velocity,
                    'context' => ['examples' => array_slice($signal['examples'], 0, 5), 'sources' => array_slice(array_keys($signal['sources']), 0, 20)],
                    'first_seen_at' => $topic->exists ? $topic->first_seen_at : $signal['first_seen_at'],
                    'last_seen_at' => $signal['last_seen_at'],
                    'analyzed_at' => $analyzedAt,
                ])->save();
                TrendSnapshot::query()->updateOrCreate(
                    ['trend_topic_id' => $topic->id, 'period_end' => $periodEnd],
                    ['mention_count' => $signal['count'], 'source_count' => $sourceCount, 'score' => $score, 'velocity' => $velocity, 'period_start' => $periodStart],
                );
            }, 3);
            $topicCount++;
        }

        TrendTopic::query()->where('agency_id', $agencyId)->where('analyzed_at', '<', $analyzedAt)->update(['status' => TrendStatus::Cooling, 'velocity' => -100, 'analyzed_at' => $analyzedAt]);

        return ['topics' => $topicCount, 'signals' => count($current)];
    }

    /** @param array<string, array{name: string, count: int, sources: array<string, true>, examples: array<int, string>, first_seen_at: CarbonImmutable, last_seen_at: CarbonImmutable}> $signals */
    private function recordTitleSignals(array &$signals, string $title, string $source, CarbonImmutable $seenAt): void
    {
        $terms = array_unique(array_filter(explode(' ', $this->normalize($title)), fn (string $term): bool => mb_strlen($term) >= 4 && ! in_array($term, self::STOP_WORDS, true)));
        foreach ($terms as $term) {
            $this->recordSignal($signals, $term, $source, $seenAt, 1, $title);
        }
    }

    /** @param array<string, array{name: string, count: int, sources: array<string, true>, examples: array<int, string>, first_seen_at: CarbonImmutable, last_seen_at: CarbonImmutable}> $signals */
    private function recordSignal(array &$signals, string $name, string $source, CarbonImmutable $seenAt, int $weight = 1, ?string $example = null): void
    {
        $normalized = $this->normalize($name);
        if ($normalized === '' || mb_strlen($normalized) < 4) {
            return;
        }
        $signals[$normalized] ??= ['name' => Str::title(Str::lower(trim($name))), 'count' => 0, 'sources' => [], 'examples' => [], 'first_seen_at' => $seenAt, 'last_seen_at' => $seenAt];
        $signals[$normalized]['count'] += $weight;
        $signals[$normalized]['sources'][$source] = true;
        $signals[$normalized]['first_seen_at'] = $seenAt->min($signals[$normalized]['first_seen_at']);
        $signals[$normalized]['last_seen_at'] = $seenAt->max($signals[$normalized]['last_seen_at']);
        if ($example && ! in_array($example, $signals[$normalized]['examples'], true)) {
            $signals[$normalized]['examples'][] = $example;
        }
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish();
    }

    private function status(float $score, float $velocity, int $sourceCount): TrendStatus
    {
        if ($score >= 70 && $sourceCount >= 3) {
            return TrendStatus::Hot;
        }
        if ($velocity >= 50) {
            return TrendStatus::Rising;
        }
        if ($velocity < 0) {
            return TrendStatus::Cooling;
        }

        return TrendStatus::Stable;
    }
}
