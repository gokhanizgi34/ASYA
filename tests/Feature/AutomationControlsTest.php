<?php

namespace Tests\Feature;

use App\IntegrationProvider;
use App\Jobs\ProcessContentBatch;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\PublishingTarget;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_owner_can_toggle_ai_visual_engine_for_own_agency(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)
            ->patch(route('visual-assets.ai-status'), [
                'agency_id' => Agency::factory()->create()->id,
                'enabled' => true,
            ])
            ->assertRedirect(route('visual-assets.index', ['agency_id' => $agency->id]));

        $this->assertDatabaseHas('system_settings', [
            'scope_key' => 'agency:'.$agency->id,
            'key' => 'visual.ai_generation_enabled',
            'value' => '1',
        ]);

        $this->actingAs($owner)
            ->get(route('visual-assets.index'))
            ->assertOk()
            ->assertSee('AI görsel motoru aktif');
    }

    public function test_agency_owner_can_set_google_trends_daily_quota(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)
            ->patch(route('trends.google-quota'), [
                'daily_limit' => 7,
            ])
            ->assertRedirect(route('trends.index', ['agency_id' => $agency->id]));

        $this->assertDatabaseHas('system_settings', [
            'scope_key' => 'agency:'.$agency->id,
            'key' => 'trends.google_daily_item_limit',
            'value' => '7',
        ]);

        $this->actingAs($owner)
            ->get(route('trends.index'))
            ->assertOk()
            ->assertSee('Google Trends günlük kotası')
            ->assertSee('value="7"', false);
    }

    public function test_queue_all_creates_real_content_batches_for_all_eligible_own_agency_items(): void
    {
        Queue::fake([ProcessContentBatch::class]);
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        ApiIntegration::factory()->ai(IntegrationProvider::GoogleGemini)->for($agency)->create();
        PublishingTarget::factory()->for($agency)->create(['is_active' => true]);
        $pending = RawNewsItem::factory()->for($agency)->create();
        $failed = RawNewsItem::factory()->for($agency)->failed()->create();
        $processed = RawNewsItem::factory()->for($agency)->processed()->create();

        $this->actingAs($owner)
            ->patch(route('raw-news.all-action'), ['action' => 'queue_all'])
            ->assertRedirect(route('raw-news.index'));

        $this->assertSame(RawNewsStatus::Queued, $pending->fresh()->status);
        $this->assertSame(RawNewsStatus::Queued, $failed->fresh()->status);
        $this->assertSame(RawNewsStatus::Processed, $processed->fresh()->status);
        $this->assertDatabaseHas('content_batches', ['agency_id' => $agency->id, 'total_items' => 2]);
        Queue::assertPushedOn('content', ProcessContentBatch::class);
    }

    public function test_retry_all_clears_failures_and_does_not_touch_another_agency(): void
    {
        Queue::fake([ProcessContentBatch::class]);
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        ApiIntegration::factory()->ai(IntegrationProvider::GoogleGemini)->for($agency)->create();
        PublishingTarget::factory()->for($agency)->create(['is_active' => true]);
        $failed = RawNewsItem::factory()->for($agency)->failed()->create(['failure_message' => 'Geçici hata']);
        $otherFailed = RawNewsItem::factory()->for($otherAgency)->failed()->create(['failure_message' => 'Başka ajans']);

        $this->actingAs($owner)
            ->patch(route('raw-news.all-action'), ['action' => 'retry_all'])
            ->assertRedirect(route('raw-news.index'));

        $this->assertSame(RawNewsStatus::Queued, $failed->fresh()->status);
        $this->assertNull($failed->fresh()->failure_message);
        $this->assertSame(RawNewsStatus::Failed, $otherFailed->fresh()->status);
        $this->assertSame('Başka ajans', $otherFailed->fresh()->failure_message);
    }

    public function test_editor_cannot_change_automation_settings(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)
            ->patch(route('visual-assets.ai-status'), ['enabled' => true])
            ->assertForbidden();
        $this->actingAs($editor)
            ->patch(route('trends.google-quota'), ['daily_limit' => 5])
            ->assertForbidden();
        $this->assertDatabaseCount('system_settings', 0);
    }
}
