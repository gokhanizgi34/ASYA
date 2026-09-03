<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\RawNewsItem;
use App\Models\TrendSnapshot;
use App\Models\TrendTopic;
use App\Services\TrendAnalyzer;
use App\TrendStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyzer_compares_periods_scores_sources_and_writes_idempotent_snapshot(): void
    {
        $agency = Agency::factory()->create();
        $now = CarbonImmutable::parse('2026-08-28 12:07:00');
        RawNewsItem::factory()->for($agency)->create(['source_name' => 'Kaynak A', 'original_title' => 'Deprem hazırlığı için yeni karar', 'discovered_at' => $now->subHours(2)]);
        RawNewsItem::factory()->for($agency)->create(['source_name' => 'Kaynak B', 'original_title' => 'Deprem bölgesinde hazırlık çalışması', 'discovered_at' => $now->subHours(3)]);
        RawNewsItem::factory()->for($agency)->create(['source_name' => 'Kaynak C', 'original_title' => 'Deprem sonrası hazırlık toplantısı', 'discovered_at' => $now->subHours(4)]);
        RawNewsItem::factory()->for($agency)->create(['source_name' => 'Eski Kaynak', 'original_title' => 'Deprem gündemi değerlendirildi', 'discovered_at' => $now->subHours(30)]);

        $result = app(TrendAnalyzer::class)->analyze($agency->id, $now);
        $topic = TrendTopic::query()->where('normalized_name', 'deprem')->firstOrFail();

        $this->assertGreaterThanOrEqual(1, $result['topics']);
        $this->assertSame(3, $topic->mention_count);
        $this->assertSame(3, $topic->source_count);
        $this->assertSame(200.0, $topic->velocity);
        $this->assertSame(TrendStatus::Rising, $topic->status);
        $this->assertDatabaseCount('trend_snapshots', TrendTopic::query()->count());

        app(TrendAnalyzer::class)->analyze($agency->id, $now->addMinutes(5));
        $this->assertSame(1, TrendSnapshot::query()->where('trend_topic_id', $topic->id)->count());
    }

    public function test_analyzer_keeps_agency_signals_isolated_and_marks_missing_topic_cooling(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $now = CarbonImmutable::parse('2026-08-28 12:00:00');
        RawNewsItem::factory()->count(2)->for($otherAgency)->create(['original_title' => 'Ekonomi piyasaları yükseliyor', 'discovered_at' => $now->subHour()]);
        $oldTopic = TrendTopic::factory()->for($agency)->create(['normalized_name' => 'eski konu', 'analyzed_at' => $now->subHour()]);

        app(TrendAnalyzer::class)->analyze($agency->id, $now);

        $this->assertDatabaseMissing('trend_topics', ['agency_id' => $agency->id, 'normalized_name' => 'ekonomi']);
        $this->assertSame(TrendStatus::Cooling, $oldTopic->fresh()->status);
        $this->assertSame(-100.0, $oldTopic->fresh()->velocity);
    }
}
