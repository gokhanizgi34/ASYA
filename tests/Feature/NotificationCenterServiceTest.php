<?php

namespace Tests\Feature;

use App\ErrorSeverity;
use App\Models\Agency;
use App\Models\Article;
use App\Models\ErrorLog;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\NotificationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_failure_notifies_administrators_and_same_agency_owners_once_per_recipient(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $administrator = User::factory()->systemAdministrator()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        User::factory()->agencyOwner()->for($otherAgency)->create();
        User::factory()->editor()->for($agency)->create();
        $article = Article::factory()->for($agency)->create(['title' => 'Bildirim Haberi']);
        $target = PublishingTarget::factory()->for($agency)->create(['name' => 'Ana Site']);
        $publication = Publication::factory()->for($agency)->for($article)->for($target, 'publishingTarget')->create([
            'failure_message' => '<b>Bağlantı kurulamadı</b>',
        ]);

        $center = app(NotificationCenter::class);
        $center->publicationFailed($publication);
        SystemNotification::query()->where('recipient_user_id', $owner->id)->update(['read_at' => now()]);
        $center->publicationFailed($publication);

        $this->assertDatabaseCount('system_notifications', 2);
        foreach ([$administrator, $owner] as $recipient) {
            $notification = SystemNotification::query()->where('recipient_user_id', $recipient->id)->sole();
            $this->assertSame(2, $notification->occurrences);
            $this->assertNull($notification->read_at);
            $this->assertStringNotContainsString('<b>', $notification->message);
            $this->assertSame('publications.show', $notification->action_route);
        }
    }

    public function test_error_notification_respects_severity_and_tenant_recipients(): void
    {
        $agency = Agency::factory()->create();
        $administrator = User::factory()->systemAdministrator()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $warning = ErrorLog::factory()->for($agency)->create(['severity' => ErrorSeverity::Warning]);
        $error = ErrorLog::factory()->for($agency)->create(['severity' => ErrorSeverity::Error]);

        $center = app(NotificationCenter::class);
        $center->errorRecorded($warning);
        $center->errorRecorded($error);

        $this->assertDatabaseCount('system_notifications', 2);
        $this->assertDatabaseHas('system_notifications', ['recipient_user_id' => $administrator->id, 'agency_id' => $agency->id]);
        $this->assertDatabaseHas('system_notifications', ['recipient_user_id' => $owner->id, 'agency_id' => $agency->id]);
    }
}
