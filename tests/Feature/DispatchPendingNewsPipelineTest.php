<?php

namespace Tests\Feature;

use App\ContentBatchItemStatus;
use App\IntegrationProvider;
use App\Jobs\ProcessContentBatch;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\ContentBatch;
use App\Models\ContentBatchItem;
use App\Models\PublishingTarget;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchPendingNewsPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_and_ai_configuration_failures_are_automatically_requeued(): void
    {
        Queue::fake([ProcessContentBatch::class]);
        $agency = Agency::factory()->create();
        User::factory()->editor()->for($agency)->create();
        ApiIntegration::factory()->ai(IntegrationProvider::GoogleGemini)->for($agency)->create([
            'is_active' => true,
            'credential' => 'gemini-key',
        ]);
        PublishingTarget::factory()->for($agency)->create(['is_active' => true]);
        $pending = RawNewsItem::factory()->for($agency)->create(['status' => RawNewsStatus::Pending]);
        $aiFailed = RawNewsItem::factory()->for($agency)->failed()->create([
            'failure_message' => 'Otomatik haber üretimi için aktif bir yapay zekâ API entegrasyonu gereklidir.',
        ]);
        $otherFailure = RawNewsItem::factory()->for($agency)->failed()->create([
            'failure_message' => 'Kaynak içerik geçersiz.',
        ]);

        $this->artisan('news:pipeline')->assertSuccessful();

        $this->assertSame(RawNewsStatus::Queued, $pending->refresh()->status);
        $this->assertSame(RawNewsStatus::Queued, $aiFailed->refresh()->status);
        $this->assertSame(RawNewsStatus::Failed, $otherFailure->refresh()->status);
        $this->assertSame(2, ContentBatch::query()->firstOrFail()->total_items);
        Queue::assertPushedOn('content', ProcessContentBatch::class);
    }

    public function test_stale_processing_item_is_recovered_and_requeued(): void
    {
        Queue::fake([ProcessContentBatch::class]);
        $this->freezeTime();
        $agency = Agency::factory()->create();
        User::factory()->editor()->for($agency)->create();
        ApiIntegration::factory()->ai(IntegrationProvider::GoogleGemini)->for($agency)->create([
            'is_active' => true,
            'credential' => 'gemini-key',
        ]);
        PublishingTarget::factory()->for($agency)->create(['is_active' => true]);
        $stale = RawNewsItem::factory()->for($agency)->create([
            'status' => RawNewsStatus::Processing,
            'updated_at' => now()->subMinutes(20),
        ]);
        $oldBatch = ContentBatch::factory()->for($agency)->create();
        $oldItem = ContentBatchItem::factory()->for($oldBatch, 'contentBatch')->for($stale, 'rawNewsItem')->create([
            'status' => ContentBatchItemStatus::Processing,
            'started_at' => now()->subMinutes(20),
        ]);

        $this->artisan('news:pipeline')->assertSuccessful();

        $this->assertSame(ContentBatchItemStatus::Failed, $oldItem->refresh()->status);
        $this->assertSame(RawNewsStatus::Queued, $stale->refresh()->status);
        $this->assertSame(2, ContentBatch::query()->count());
        Queue::assertPushedOn('content', ProcessContentBatch::class);
    }
}
