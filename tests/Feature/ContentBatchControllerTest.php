<?php

namespace Tests\Feature;

use App\ContentBatchItemStatus;
use App\ContentBatchStatus;
use App\Jobs\ProcessContentBatch;
use App\Models\Agency;
use App\Models\AiPrompt;
use App\Models\ContentBatch;
use App\Models\ContentBatchItem;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContentBatchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_content_factory(): void
    {
        $this->get(route('content-batches.index'))->assertRedirect(route('login'));
    }

    public function test_agency_user_sees_only_own_batches_and_escaped_names(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();
        $ownBatch = ContentBatch::factory()->for($ownAgency)->create(['name' => '<script>alert(1)</script>']);
        $otherBatch = ContentBatch::factory()->for($otherAgency)->create(['name' => 'Başka Ajans Bandı']);

        $this->actingAs($owner)->get(route('content-batches.index'))
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee($otherBatch->name);
        $this->actingAs($owner)->get(route('content-batches.show', $otherBatch))->assertForbidden();
        $this->assertModelExists($ownBatch);
    }

    public function test_valid_request_creates_batch_snapshots_prompt_and_dispatches_job(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $prompt = AiPrompt::factory()->global()->create(['name' => 'Spor Promptu', 'version' => 4, 'target_length' => 450]);
        $rawNewsItems = RawNewsItem::factory()->count(2)->for($agency)->create();
        Queue::fake([ProcessContentBatch::class]);

        $response = $this->actingAs($editor)->post(route('content-batches.store'), [
            'agency_id' => $otherAgency->id,
            'name' => 'Sabah Spor Bandı',
            'ai_prompt_id' => $prompt->id,
            'raw_news_ids' => $rawNewsItems->modelKeys(),
        ]);

        $batch = ContentBatch::query()->where('name', 'Sabah Spor Bandı')->firstOrFail();
        $response->assertRedirect(route('content-batches.show', $batch));
        $this->assertSame($agency->id, $batch->agency_id);
        $this->assertSame(2, $batch->total_items);
        $this->assertSame(4, data_get($batch->settings, 'prompt_snapshot.version'));
        $this->assertDatabaseCount('content_batch_items', 2);
        $this->assertSame(2, RawNewsItem::query()->where('status', RawNewsStatus::Queued)->count());
        Queue::assertPushedOn('content', ProcessContentBatch::class, fn (ProcessContentBatch $job): bool => $job->contentBatchId === $batch->id);
    }

    public function test_request_rejects_raw_news_from_another_agency_without_side_effects(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $prompt = AiPrompt::factory()->global()->create();
        $otherRawNews = RawNewsItem::factory()->for($otherAgency)->create();
        Queue::fake([ProcessContentBatch::class]);

        $this->actingAs($owner)->post(route('content-batches.store'), [
            'agency_id' => $agency->id,
            'name' => 'Geçersiz Band',
            'ai_prompt_id' => $prompt->id,
            'raw_news_ids' => [$otherRawNews->id],
        ])->assertSessionHasErrors([
            'raw_news_ids' => 'Tüm ham haberler aynı ajansa ait ve bekleyen veya hatalı durumda olmalıdır.',
        ]);

        $this->assertDatabaseCount('content_batches', 0);
        Queue::assertNothingPushed();
    }

    public function test_request_rejects_another_agencys_prompt(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $prompt = AiPrompt::factory()->for($otherAgency)->create();
        $rawNews = RawNewsItem::factory()->for($agency)->create();

        $this->actingAs($owner)->post(route('content-batches.store'), [
            'agency_id' => $agency->id,
            'name' => 'Geçersiz Prompt Bandı',
            'ai_prompt_id' => $prompt->id,
            'raw_news_ids' => [$rawNews->id],
        ])->assertSessionHasErrors([
            'ai_prompt_id' => 'Seçilen prompt bu ajans tarafından kullanılamaz.',
        ]);

        $this->assertDatabaseCount('content_batches', 0);
    }

    public function test_processed_raw_news_cannot_enter_a_new_batch(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $prompt = AiPrompt::factory()->global()->create();
        $processedRawNews = RawNewsItem::factory()->for($agency)->processed()->create();

        $this->actingAs($owner)->post(route('content-batches.store'), [
            'agency_id' => $agency->id,
            'name' => 'İkinci İşleme',
            'ai_prompt_id' => $prompt->id,
            'raw_news_ids' => [$processedRawNews->id],
        ])->assertSessionHasErrors('raw_news_ids');

        $this->assertDatabaseCount('content_batches', 0);
    }

    public function test_failed_batch_can_be_reset_and_dispatched_again(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $rawNews = RawNewsItem::factory()->for($agency)->failed()->create();
        $batch = ContentBatch::factory()->for($agency)->create([
            'status' => ContentBatchStatus::Failed,
            'total_items' => 1,
            'failed_items' => 1,
        ]);
        $item = ContentBatchItem::factory()->for($batch)->for($rawNews, 'rawNewsItem')->create([
            'status' => ContentBatchItemStatus::Failed,
            'failure_message' => 'Önceki hata',
        ]);
        Queue::fake([ProcessContentBatch::class]);

        $this->actingAs($owner)->post(route('content-batches.dispatch', $batch))->assertRedirect();

        $this->assertSame(ContentBatchStatus::Queued, $batch->fresh()->status);
        $this->assertSame(ContentBatchItemStatus::Queued, $item->fresh()->status);
        Queue::assertPushedOn('content', ProcessContentBatch::class);
    }

    public function test_completed_batch_cannot_be_dispatched_again(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $batch = ContentBatch::factory()->for($agency)->completed()->create();
        Queue::fake([ProcessContentBatch::class]);

        $this->actingAs($owner)->post(route('content-batches.dispatch', $batch))->assertForbidden();

        Queue::assertNothingPushed();
    }
}
