<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AnalyticsSnapshot;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\CampaignContent;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\RawNewsItem;
use App\Models\SeoAnalysis;
use App\Models\TrendSnapshot;
use App\Models\TrendTopic;
use App\Models\User;
use App\PublicationStatus;
use App\Services\AnalyticsAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsAggregatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregator_counts_only_requested_agency_and_day_then_updates_idempotently(): void
    {
        $this->travelTo('2026-08-28 18:00:00');
        $date = CarbonImmutable::parse('2026-08-28');
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $user = User::factory()->agencyOwner()->for($agency)->create();
        RawNewsItem::factory()->count(2)->for($agency)->create(['discovered_at' => '2026-08-28 09:00:00']);
        RawNewsItem::factory()->for($otherAgency)->create(['discovered_at' => '2026-08-28 09:00:00']);
        $article = Article::factory()->for($agency)->published()->create(['created_at' => '2026-08-28 10:00:00', 'published_at' => '2026-08-28 11:00:00']);
        Article::factory()->for($agency)->create(['created_at' => '2026-08-27 10:00:00']);
        SeoAnalysis::factory()->for($article)->for($agency)->create(['score' => 80, 'word_count' => 600, 'analyzed_at' => '2026-08-28 10:30:00']);
        $target = PublishingTarget::factory()->for($agency)->create();
        Publication::factory()->for($agency)->for($article)->for($target, 'publishingTarget')->for($user, 'creator')->create(['status' => PublicationStatus::Published, 'published_at' => '2026-08-28 12:00:00', 'completed_at' => '2026-08-28 12:00:00']);
        Publication::factory()->for($agency)->for($target, 'publishingTarget')->for($user, 'creator')->create(['status' => PublicationStatus::Failed, 'completed_at' => '2026-08-28 13:00:00']);
        $campaign = Campaign::factory()->for($agency)->for($user, 'owner')->create(['created_at' => '2026-08-28 14:00:00']);
        CampaignContent::factory()->for($campaign)->for($user, 'creator')->create(['created_at' => '2026-08-28 14:30:00']);
        $topic = TrendTopic::factory()->for($agency)->create();
        TrendSnapshot::factory()->for($topic)->create(['score' => 100, 'period_start' => '2026-08-28 15:00:00', 'period_end' => '2026-08-28 15:15:00']);

        $snapshot = app(AnalyticsAggregator::class)->aggregate($agency->id, $date);

        $this->assertSame(2, $snapshot->raw_news_count);
        $this->assertSame(1, $snapshot->articles_created_count);
        $this->assertSame(1, $snapshot->articles_published_count);
        $this->assertSame(1, $snapshot->publication_success_count);
        $this->assertSame(1, $snapshot->publication_failure_count);
        $this->assertSame(50.0, $snapshot->publicationSuccessRate());
        $this->assertSame(1, $snapshot->campaigns_created_count);
        $this->assertSame(1, $snapshot->campaign_contents_count);
        $this->assertSame(1, $snapshot->trend_topics_count);
        $this->assertSame(600, $snapshot->seo_word_count);
        $this->assertSame(80.0, $snapshot->average_seo_score);
        $this->assertSame(100.0, $snapshot->average_trend_score);

        RawNewsItem::factory()->for($agency)->create(['discovered_at' => '2026-08-28 16:00:00']);
        app(AnalyticsAggregator::class)->aggregate($agency->id, $date);
        $this->assertDatabaseCount('analytics_snapshots', 1);
        $this->assertSame(3, AnalyticsSnapshot::query()->firstOrFail()->raw_news_count);
    }

    public function test_empty_day_produces_zero_counts_without_fabricated_averages(): void
    {
        $agency = Agency::factory()->create();

        $snapshot = app(AnalyticsAggregator::class)->aggregate($agency->id, CarbonImmutable::parse('2026-08-01'));

        $this->assertSame(0, $snapshot->raw_news_count);
        $this->assertSame(0.0, $snapshot->publicationSuccessRate());
        $this->assertNull($snapshot->average_seo_score);
        $this->assertNull($snapshot->average_trend_score);
    }
}
