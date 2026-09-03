<?php

namespace Tests\Feature;

use App\ErrorSeverity;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_user_sees_only_personal_notifications_safely(): void
    {
        $user = User::factory()->editor()->create();
        $other = User::factory()->editor()->create();
        $own = SystemNotification::factory()->for($user, 'recipient')->create([
            'title' => '<script>alert(1)</script>',
        ]);
        $foreign = SystemNotification::factory()->for($other, 'recipient')->create();

        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee($foreign->title)
            ->assertSee($own->message);
    }

    public function test_user_can_mark_own_notification_but_not_another_users_notification(): void
    {
        $user = User::factory()->editor()->create();
        $other = User::factory()->editor()->create();
        $own = SystemNotification::factory()->for($user, 'recipient')->create();
        $foreign = SystemNotification::factory()->for($other, 'recipient')->create();

        $this->actingAs($user)->patch(route('notifications.read', $foreign))->assertForbidden();
        $this->actingAs($user)->patch(route('notifications.read', $own))->assertRedirect();

        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_mark_all_read_updates_only_current_users_notifications(): void
    {
        $user = User::factory()->agencyOwner()->create();
        $other = User::factory()->agencyOwner()->create();
        SystemNotification::factory()->count(2)->for($user, 'recipient')->create();
        $foreign = SystemNotification::factory()->for($other, 'recipient')->create();

        $this->actingAs($user)->patch(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, SystemNotification::query()->where('recipient_user_id', $user->id)->whereNull('read_at')->count());
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_notification_list_filters_unread_and_severity(): void
    {
        $user = User::factory()->systemAdministrator()->create();
        $matching = SystemNotification::factory()->for($user, 'recipient')->create(['severity' => ErrorSeverity::Critical, 'title' => 'KRITIK-BILDIRIM']);
        $read = SystemNotification::factory()->read()->for($user, 'recipient')->create(['severity' => ErrorSeverity::Critical, 'title' => 'OKUNMUS']);
        $warning = SystemNotification::factory()->for($user, 'recipient')->create(['severity' => ErrorSeverity::Warning, 'title' => 'UYARI']);

        $this->actingAs($user)->get(route('notifications.index', ['severity' => 'critical', 'unread' => 1]))
            ->assertOk()
            ->assertSee($matching->title)
            ->assertDontSee($read->title)
            ->assertDontSee($warning->title);
    }
}
