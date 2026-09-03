<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsReportRequest;
use App\Models\Agency;
use App\Models\AnalyticsSnapshot;
use App\Models\NewsSource;
use App\Models\User;
use App\PublicationStatus;
use App\RawNewsStatus;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __invoke(AnalyticsReportRequest $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validated();
        $from = CarbonImmutable::parse($data['from'])->startOfDay();
        $to = CarbonImmutable::parse($data['to'])->endOfDay();
        $baseQuery = AnalyticsSnapshot::query()->visibleTo($user)
            ->whereDate('report_date', '>=', $data['from'])
            ->whereDate('report_date', '<=', $data['to'])
            ->when($data['agency_id'] ?? null, fn ($query, $agencyId) => $query->where('agency_id', $agencyId));
        $totals = (clone $baseQuery)->toBase()
            ->selectRaw('COALESCE(SUM(raw_news_count), 0) as raw_news')
            ->selectRaw('COALESCE(SUM(articles_created_count), 0) as articles')
            ->selectRaw('COALESCE(SUM(articles_published_count), 0) as published')
            ->selectRaw('COALESCE(SUM(publication_success_count), 0) as publication_success')
            ->selectRaw('COALESCE(SUM(publication_failure_count), 0) as publication_failure')
            ->selectRaw('COALESCE(AVG(average_seo_score), 0) as average_seo_score')
            ->selectRaw('COALESCE(SUM(campaigns_created_count), 0) as campaigns')
            ->selectRaw('COALESCE(SUM(trend_topics_count), 0) as trends')
            ->first();
        $series = (clone $baseQuery)->toBase()->select('report_date')
            ->selectRaw('SUM(raw_news_count) as raw_news, SUM(articles_created_count) as articles, SUM(articles_published_count) as published, SUM(publication_success_count) as success, SUM(publication_failure_count) as failure')
            ->groupBy('report_date')->orderBy('report_date')->get()->map(fn ($day): array => ['date' => substr((string) $day->report_date, 0, 10), 'raw_news' => (int) $day->raw_news, 'articles' => (int) $day->articles, 'published' => (int) $day->published, 'success' => (int) $day->success, 'failure' => (int) $day->failure]);
        $publicationTotal = (int) $totals->publication_success + (int) $totals->publication_failure;
        $sourcePerformance = NewsSource::query()->visibleTo($user)
            ->when($data['agency_id'] ?? null, fn ($query, $agencyId) => $query->where('agency_id', $agencyId))
            ->withCount([
                'rawNewsItems as collected_count' => fn ($query) => $query->whereBetween('created_at', [$from, $to]),
                'rawNewsItems as failed_count' => fn ($query) => $query->whereBetween('created_at', [$from, $to])->where('status', RawNewsStatus::Failed),
                'rawNewsItems as rejected_count' => fn ($query) => $query->whereBetween('created_at', [$from, $to])->where('status', RawNewsStatus::Rejected),
                'rawNewsItems as missing_image_count' => fn ($query) => $query->whereBetween('created_at', [$from, $to])->whereNull('original_image_url'),
            ])
            ->selectSub(function ($query) use ($from, $to): void {
                $query->from('publications as source_publications')
                    ->join('content_batch_items as source_batch_items', 'source_batch_items.article_id', '=', 'source_publications.article_id')
                    ->join('raw_news_items as source_raw_news', 'source_raw_news.id', '=', 'source_batch_items.raw_news_item_id')
                    ->whereColumn('source_raw_news.news_source_id', 'news_sources.id')
                    ->where('source_publications.status', PublicationStatus::Published)
                    ->whereBetween('source_raw_news.created_at', [$from, $to])
                    ->selectRaw('COUNT(DISTINCT source_publications.id)');
            }, 'published_count')
            ->orderByDesc('published_count')
            ->orderByDesc('collected_count')
            ->orderBy('name')
            ->get();

        return view('analytics.index', [
            'snapshots' => (clone $baseQuery)->with('agency')->orderByDesc('report_date')->orderBy('agency_id')->paginate(50)->withQueryString(),
            'series' => $series,
            'summary' => ['raw_news' => (int) $totals->raw_news, 'articles' => (int) $totals->articles, 'published' => (int) $totals->published, 'publication_success_rate' => $publicationTotal === 0 ? 0 : round(((int) $totals->publication_success / $publicationTotal) * 100, 2), 'average_seo_score' => round((float) $totals->average_seo_score, 2), 'campaigns' => (int) $totals->campaigns, 'trends' => (int) $totals->trends],
            'agencies' => Agency::query()->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get(),
            'maxPublished' => max(1, (int) $series->max('published')),
            'sourcePerformance' => $sourcePerformance,
        ]);
    }
}
