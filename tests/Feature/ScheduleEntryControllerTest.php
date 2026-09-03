<?php

namespace Tests\Feature;

use App\CampaignStatus;
use App\Jobs\ProcessScheduleEntry;
use App\Models\Agency;
use App\Models\Campaign;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\ScheduleAction;
use App\ScheduleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_agency_user_sees_only_own_plans(): void
    {
        $this->get(route('schedules.index'))->assertRedirect(route('login'));
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($editor, 'owner')->create();
        $own = ScheduleEntry::factory()->for($agency)->for($editor, 'creator')->for($campaign)->create(['title' => '<script>alert(1)</script>']);
        $other = ScheduleEntry::factory()->for($otherAgency)->create(['title' => 'Başka Ajans Planı']);

        $this->actingAs($editor)->get(route('schedules.index'))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee($other->title);
        $this->actingAs($editor)->get(route('schedules.show', $other))->assertForbidden();
        $this->assertModelExists($own);
    }

    public function test_editor_schedules_own_scheduled_campaign_and_cannot_spoof_agency(): void
    {
        $this->travelTo('2026-08-28 12:00:00');
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($editor, 'owner')->create(['status' => CampaignStatus::Scheduled]);

        $response = $this->actingAs($editor)->post(route('schedules.store'), ['agency_id' => $otherAgency->id, 'action' => ScheduleAction::ActivateCampaign->value, 'campaign_id' => $campaign->id, 'scheduled_for' => '2026-08-28 15:00', 'timezone' => 'UTC']);

        $entry = ScheduleEntry::query()->firstOrFail();
        $response->assertRedirect(route('schedules.show', $entry));
        $this->assertSame($agency->id, $entry->agency_id);
        $this->assertSame('2026-08-28 18:00:00', $entry->scheduled_for->format('Y-m-d H:i:s'));
        $this->assertSame('campaign:'.$campaign->id.':activate_campaign', $entry->active_key);
    }

    public function test_action_rejects_wrong_campaign_state_and_cross_tenant_resource(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $draft = Campaign::factory()->for($agency)->for($editor, 'owner')->create(['status' => CampaignStatus::Draft]);
        $other = Campaign::factory()->for($otherAgency)->create(['status' => CampaignStatus::Scheduled]);

        $this->actingAs($editor)->post(route('schedules.store'), $this->campaignPayload($agency, $draft))->assertSessionHasErrors('campaign_id');
        $this->actingAs($editor)->post(route('schedules.store'), $this->campaignPayload($agency, $other))->assertSessionHasErrors('campaign_id');
        $this->assertDatabaseCount('schedule_entries', 0);
    }

    public function test_duplicate_active_plan_is_rejected_and_cancel_releases_conflict_key(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($editor, 'owner')->create(['status' => CampaignStatus::Scheduled]);
        $entry = ScheduleEntry::factory()->for($agency)->for($editor, 'creator')->for($campaign)->create(['active_key' => 'campaign:'.$campaign->id.':activate_campaign']);

        $this->actingAs($editor)->post(route('schedules.store'), $this->campaignPayload($agency, $campaign))->assertSessionHasErrors('scheduled_for');
        $this->actingAs($editor)->patch(route('schedules.status', $entry), ['operation' => 'cancel'])->assertRedirect();
        $this->assertSame(ScheduleStatus::Cancelled, $entry->fresh()->status);
        $this->assertNull($entry->fresh()->active_key);
    }

    public function test_due_command_queues_only_due_pending_plans(): void
    {
        Queue::fake([ProcessScheduleEntry::class]);
        ScheduleEntry::factory()->due()->create();
        ScheduleEntry::factory()->create(['scheduled_for' => now()->addHour()]);
        ScheduleEntry::factory()->failed()->create(['active_key' => 'failed:plan']);

        $this->artisan('schedules:run')->assertSuccessful();

        Queue::assertPushedOn('scheduling', ProcessScheduleEntry::class);
        Queue::assertPushed(ProcessScheduleEntry::class, 1);
    }

    /** @return array<string, mixed> */
    private function campaignPayload(Agency $agency, Campaign $campaign): array
    {
        return ['agency_id' => $agency->id, 'action' => ScheduleAction::ActivateCampaign->value, 'campaign_id' => $campaign->id, 'scheduled_for' => now()->addHour()->format('Y-m-d H:i:s'), 'timezone' => 'Europe/Istanbul'];
    }
}
