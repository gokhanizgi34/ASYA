<?php

namespace Tests\Feature;

use App\CampaignStatus;
use App\Jobs\ProcessScheduleEntry;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\PublicationStatus;
use App\ScheduleAction;
use App\ScheduleStatus;
use App\Services\CampaignWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessScheduleEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_publication_plan_resets_failure_dispatches_publisher_and_completes(): void
    {
        Queue::fake([PublishArticleToWordPress::class]);
        $agency = Agency::factory()->create();
        $user = User::factory()->agencyOwner()->for($agency)->create();
        $target = PublishingTarget::factory()->for($agency)->create();
        $publication = Publication::factory()->for($agency)->for($target, 'publishingTarget')->for($user, 'creator')->failed()->create();
        $entry = ScheduleEntry::factory()->for($agency)->for($user, 'creator')->due()->create(['campaign_id' => null, 'publication_id' => $publication->id, 'action' => ScheduleAction::PublishWordPress, 'active_key' => 'publication:'.$publication->id]);

        (new ProcessScheduleEntry($entry->id))->handle(app(CampaignWorkflow::class));

        $this->assertSame(ScheduleStatus::Completed, $entry->fresh()->status);
        $this->assertNull($entry->fresh()->active_key);
        $this->assertSame(PublicationStatus::Queued, $publication->fresh()->status);
        Queue::assertPushedOn('publishing', PublishArticleToWordPress::class, fn (PublishArticleToWordPress $job): bool => $job->publicationId === $publication->id);
    }

    public function test_due_campaign_plan_activates_campaign_and_completes(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($owner, 'owner')->create(['status' => CampaignStatus::Scheduled]);
        $entry = ScheduleEntry::factory()->for($agency)->for($owner, 'creator')->for($campaign)->due()->create(['action' => ScheduleAction::ActivateCampaign, 'active_key' => 'campaign:'.$campaign->id.':activate_campaign']);

        (new ProcessScheduleEntry($entry->id))->handle(app(CampaignWorkflow::class));

        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);
        $this->assertSame(ScheduleStatus::Completed, $entry->fresh()->status);
        $this->assertSame(1, $entry->fresh()->attempt_count);
    }

    public function test_invalid_resource_state_is_persisted_as_failed_without_throwing(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($owner, 'owner')->create(['status' => CampaignStatus::Draft]);
        $entry = ScheduleEntry::factory()->for($agency)->for($owner, 'creator')->for($campaign)->due()->create(['action' => ScheduleAction::ActivateCampaign, 'active_key' => 'campaign:'.$campaign->id.':activate_campaign']);

        (new ProcessScheduleEntry($entry->id))->handle(app(CampaignWorkflow::class));

        $this->assertSame(ScheduleStatus::Failed, $entry->fresh()->status);
        $this->assertNotNull($entry->fresh()->failure_message);
        $this->assertNotNull($entry->fresh()->active_key);
    }

    public function test_future_plan_is_not_processed(): void
    {
        $entry = ScheduleEntry::factory()->create(['scheduled_for' => now()->addHour()]);

        (new ProcessScheduleEntry($entry->id))->handle(app(CampaignWorkflow::class));

        $this->assertSame(ScheduleStatus::Pending, $entry->fresh()->status);
        $this->assertSame(0, $entry->fresh()->attempt_count);
    }
}
