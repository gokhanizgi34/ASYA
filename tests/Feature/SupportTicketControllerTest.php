<?php

namespace Tests\Feature;

use App\Mail\SupportTicketCreatedMail;
use App\Models\Agency;
use App\Models\AgencyMailSetting;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupportTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_creates_ticket_and_notification_mail_is_sent(): void
    {
        Mail::fake();
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        AgencyMailSetting::factory()->for($agency)->create(['notification_email' => 'support@example.com']);
        $response = $this->actingAs($editor)->post(route('support-tickets.store'), ['category' => 'technical', 'priority' => 'high', 'subject' => 'Yayın işlemi tamamlanmıyor', 'message' => 'Yayın düğmesine bastığımda işlem tamamlanmadan kalıyor.']);
        $ticket = SupportTicket::query()->sole();
        $response->assertRedirect(route('support-tickets.show', $ticket));
        $this->assertSame($editor->id, $ticket->user_id);
        Mail::assertSent(SupportTicketCreatedMail::class, fn ($mail) => $mail->hasTo('support@example.com'));
    }

    public function test_editor_cannot_view_another_users_ticket(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $other = User::factory()->editor()->for($agency)->create();
        $ticket = SupportTicket::factory()->for($agency)->for($other, 'requester')->create();
        $this->actingAs($editor)->get(route('support-tickets.show', $ticket))->assertForbidden();
    }

    public function test_system_administrator_updates_ticket_status(): void
    {
        $admin = User::factory()->systemAdministrator()->create();
        $ticket = SupportTicket::factory()->create();
        $this->actingAs($admin)->patch(route('support-tickets.update', $ticket), ['status' => 'resolved', 'admin_note' => 'Sorun giderildi.'])->assertRedirect();
        $this->assertSame('resolved', $ticket->refresh()->status->value);
        $this->assertSame($admin->id, $ticket->handled_by);
    }
}
