<?php

namespace App\Services;

use App\Models\AnalyticsSnapshot;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\CampaignContent;
use App\Models\Publication;
use App\Models\RawNewsItem;
use App\Models\SeoAnalysis;
use App\Models\TrendSnapshot;
use App\PublicationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AnalyticsAggregator
{
    public function aggregate(int $agencyId, CarbonImmutable $date): AnalyticsSnapshot
    {
        $start = $date->startOfDay();
        $end = $date->endOfDay();
        $rawNewsCount = RawNewsItem::query()->where('agency_id', $agencyId)->where(function ($query) use ($start, $end): void {
            $query->whereBetween('discovered_at', [$start, $end])->orWhere(fn ($nested) => $nested->whereNull('discovered_at')->whereBetween('created_at', [$start, $end]));
        })->count();
        $articlesCreated = Article::query()->where('agency_id', $agencyId)->whereBetween('created_at', [$start, $end])->count();
        $articlesPublished = Article::query()->where('agency_id', $agencyId)->whereBetween('published_at', [$start, $end])->count();
        $publicationSuccess = Publication::query()->where('agency_id', $agencyId)->where('status', PublicationStatus::Published)->whereBetween('published_at', [$start, $end])->count();
        $publicationFailure = Publication::query()->where('agency_id', $agencyId)->where('status', PublicationStatus::Failed)->whereBetween('completed_at', [$start, $end])->count();
        $campaignsCreated = Campaign::query()->withTrashed()->where('agency_id', $agencyId)->whereBetween('created_at', [$start, $end])->count();
        $campaignContents = CampaignContent::query()->whereHas('campaign', fn ($query) => $query->withTrashed()->where('agency_id', $agencyId))->whereBetween('created_at', [$start, $end])->count();
        $seoQuery = SeoAnalysis::query()->where('agency_id', $agencyId)->whereBetween('analyzed_at', [$start, $end]);
        $trendQuery = TrendSnapshot::query()->whereHas('trendTopic', fn ($query) => $query->where('agency_id', $agencyId))->whereBetween('period_end', [$start, $end]);
        $totalPublications = $publicationSuccess + $publicationFailure;

        $values = [
            'raw_news_count' => $rawNewsCount,
            'articles_created_count' => $articlesCreated,
            'articles_published_count' => $articlesPublished,
            'publication_success_count' => $publicationSuccess,
            'publication_failure_count' => $publicationFailure,
            'campaigns_created_count' => $campaignsCreated,
            'campaign_contents_count' => $campaignContents,
            'trend_topics_count' => (clone $trendQuery)->distinct()->count('trend_topic_id'),
            'seo_word_count' => (int) (clone $seoQuery)->sum('word_count'),
            'average_seo_score' => (clone $seoQuery)->avg('score'),
            'average_trend_score' => (clone $trendQuery)->avg('score'),
            'details' => ['publication_success_rate' => $totalPublications === 0 ? 0 : round(($publicationSuccess / $totalPublications) * 100, 2)],
            'aggregated_at' => now(),
        ];

        return DB::transaction(function () use ($agencyId, $start, $values): AnalyticsSnapshot {
            $snapshot = AnalyticsSnapshot::query()->where('agency_id', $agencyId)->whereDate('report_date', $start->toDateString())->first() ?? new AnalyticsSnapshot;
            $snapshot->fill([...$values, 'agency_id' => $agencyId, 'report_date' => $start->toDateString()])->save();

            return $snapshot;
        }, 3);
    }
}
