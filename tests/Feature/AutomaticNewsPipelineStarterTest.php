<?php

namespace Tests\Feature;

use App\IntegrationProvider;
use App\Jobs\ProcessContentBatch;
use App\Models\Agency;
use App\Models\AiPrompt;
use App\Models\ApiIntegration;
use App\Models\NewsSource;
use App\Models\PublishingTarget;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use App\Services\AutomaticNewsPipelineStarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomaticNewsPipelineStarterTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_pending_items_start_one_automatic_content_batch(): void
    {
        Queue::fake([ProcessContentBatch::class]);
        $agency = Agency::factory()->create();
        $creator = User::factory()->editor()->for($agency)->create();
        $prompt = AiPrompt::factory()->for($agency)->create(['is_active' => true]);
        ApiIntegration::factory()->ai(IntegrationProvider::GoogleGemini)->for($agency)->create(['is_active' => true]);
        PublishingTarget::factory()->for($agency)->create(['is_active' => true]);
        $source = NewsSource::factory()->for($agency)->create(['created_by' => $creator->id]);
        $pending = RawNewsItem::factory()->for($agency)->create(['status' => RawNewsStatus::Pending]);
        $rejected = RawNewsItem::factory()->for($agency)->create(['status' => RawNewsStatus::Rejected]);

        $batch = app(AutomaticNewsPipelineStarter::class)->start($source, [$pending->id, $rejected->id]);

        $this->assertNotNull($batch);
        $this->assertSame($prompt->id, $batch->ai_prompt_id);
        $this->assertSame(1, $batch->total_items);
        $this->assertTrue((bool) data_get($batch->settings, 'automatic_pipeline'));
        $this->assertDatabaseHas('content_batch_items', [
            'content_batch_id' => $batch->id,
            'raw_news_item_id' => $pending->id,
        ]);
        $this->assertSame(RawNewsStatus::Queued, $pending->refresh()->status);
        $this->assertSame(RawNewsStatus::Rejected, $rejected->refresh()->status);
        Queue::assertPushedOn('content', ProcessContentBatch::class);
        Queue::assertPushed(fn (ProcessContentBatch $job): bool => $job->contentBatchId === $batch->id);
    }

    public function test_pipeline_keeps_items_pending_when_ai_integration_is_missing(): void
    {
        Queue::fake([ProcessContentBatch::class]);
        $agency = Agency::factory()->create();
        $creator = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['created_by' => $creator->id]);
        $pending = RawNewsItem::factory()->for($agency)->create(['status' => RawNewsStatus::Pending]);

        $batch = app(AutomaticNewsPipelineStarter::class)->start($source, [$pending->id]);

        $this->assertNull($batch);
        $this->assertSame(RawNewsStatus::Pending, $pending->refresh()->status);
        Queue::assertNothingPushed();
    }
}
