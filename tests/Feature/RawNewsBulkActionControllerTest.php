<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawNewsBulkActionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_queue_eligible_items_in_own_agency(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $pending = RawNewsItem::factory()->for($agency)->create();
        $failed = RawNewsItem::factory()->for($agency)->failed()->create();
        $processed = RawNewsItem::factory()->for($agency)->processed()->create();

        $this->actingAs($owner)->patch(route('raw-news.bulk-action'), [
            'items' => [$pending->id, $failed->id, $processed->id],
            'action' => 'queue',
        ])->assertRedirect(route('raw-news.index'));

        $this->assertSame(RawNewsStatus::Queued, $pending->fresh()->status);
        $this->assertSame(RawNewsStatus::Queued, $failed->fresh()->status);
        $this->assertSame(RawNewsStatus::Processed, $processed->fresh()->status);
    }

    public function test_retry_returns_failed_item_to_pending_and_clears_error(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $failed = RawNewsItem::factory()->for($agency)->failed()->create();

        $this->actingAs($editor)->patch(route('raw-news.bulk-action'), [
            'items' => [$failed->id],
            'action' => 'retry',
        ])->assertRedirect(route('raw-news.index'));

        $failed->refresh();
        $this->assertSame(RawNewsStatus::Pending, $failed->status);
        $this->assertNull($failed->failure_message);
    }

    public function test_cross_agency_bulk_action_is_forbidden_atomically(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();
        $ownItem = RawNewsItem::factory()->for($ownAgency)->create();
        $otherItem = RawNewsItem::factory()->for($otherAgency)->create();

        $this->actingAs($owner)->patch(route('raw-news.bulk-action'), [
            'items' => [$ownItem->id, $otherItem->id],
            'action' => 'reject',
        ])->assertForbidden();

        $this->assertSame(RawNewsStatus::Pending, $ownItem->fresh()->status);
        $this->assertSame(RawNewsStatus::Pending, $otherItem->fresh()->status);
    }

    public function test_bulk_action_requires_valid_distinct_items_and_action(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $item = RawNewsItem::factory()->for($agency)->create();

        $this->actingAs($owner)->patch(route('raw-news.bulk-action'), [
            'items' => [$item->id, $item->id],
            'action' => 'invalid',
        ])->assertSessionHasErrors(['items.0', 'items.1', 'action']);
    }
}
