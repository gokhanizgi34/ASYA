<?php

namespace Tests\Feature;

use App\Mail\MailIntegrationTestMail;
use App\Models\Agency;
use App\Models\AgencyMailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AgencyMailSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_saves_only_own_agency_mail_credentials_encrypted(): void
    {
        $agency = Agency::factory()->create();
        $other = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $this->actingAs($owner)->post(route('agency-mail-settings.store'), $this->payload(['agency_id' => $other->id]))->assertRedirect(route('api-integrations.index'));
        $setting = AgencyMailSetting::query()->sole();
        $this->assertSame($agency->id, $setting->agency_id);
        $this->assertSame('smtp-secret', $setting->password);
        $this->assertNotSame('smtp-secret', DB::table('agency_mail_settings')->value('password'));
    }

    public function test_editor_cannot_save_mail_setting(): void
    {
        $editor = User::factory()->editor()->create();
        $this->actingAs($editor)->post(route('agency-mail-settings.store'), $this->payload())->assertForbidden();
    }

    public function test_owner_can_send_test_mail_to_configured_recipient(): void
    {
        Mail::fake();
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $setting = AgencyMailSetting::factory()->for($agency)->create(['notification_email' => 'owner@example.com']);
        $this->actingAs($owner)->post(route('agency-mail-settings.test', $setting))->assertSessionHas('success');
        Mail::assertSent(MailIntegrationTestMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['agency_id' => null, 'host' => 'smtp.example.com', 'port' => 587, 'scheme' => 'smtp', 'username' => 'mailer', 'password' => 'smtp-secret', 'from_address' => 'asya@example.com', 'from_name' => 'ASYA', 'notification_email' => 'owner@example.com', 'is_active' => true], $overrides);
    }
}
