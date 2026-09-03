<?php

namespace Tests\Feature;

use App\ErrorLogStatus;
use App\Models\Agency;
use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_editor_is_forbidden(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->get(route('error-logs.index'))->assertRedirect(route('login'));
        $this->actingAs($editor)->get(route('error-logs.index'))->assertForbidden();
    }

    public function test_agency_owner_sees_only_own_error_logs(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        ErrorLog::factory()->for($agency)->create(['message' => 'OWN-TENANT-ERROR']);
        ErrorLog::factory()->for($otherAgency)->create(['message' => 'OTHER-TENANT-ERROR']);

        $this->actingAs($owner)
            ->get(route('error-logs.index'))
            ->assertOk()
            ->assertSee('OWN-TENANT-ERROR')
            ->assertDontSee('OTHER-TENANT-ERROR');
    }

    public function test_system_administrator_can_see_tenant_and_system_errors(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        ErrorLog::factory()->create(['message' => 'TENANT-ERROR']);
        ErrorLog::factory()->system()->create(['message' => 'SYSTEM-ERROR']);

        $this->actingAs($administrator)
            ->get(route('error-logs.index'))
            ->assertOk()
            ->assertSee('TENANT-ERROR')
            ->assertSee('SYSTEM-ERROR');
    }

    public function test_owner_can_resolve_and_reopen_own_error_with_a_note(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $errorLog = ErrorLog::factory()->for($agency)->create();

        $this->actingAs($owner)
            ->patch(route('error-logs.status', $errorLog), ['operation' => 'resolve'])
            ->assertSessionHasErrors('resolution_note');

        $this->actingAs($owner)
            ->patch(route('error-logs.status', $errorLog), ['operation' => 'resolve', 'resolution_note' => 'Bağlantı ayarı düzeltildi.'])
            ->assertRedirect();

        $errorLog->refresh();
        $this->assertSame(ErrorLogStatus::Resolved, $errorLog->status);
        $this->assertSame($owner->id, $errorLog->resolved_by_id);
        $this->assertNotNull($errorLog->resolved_at);

        $this->actingAs($owner)
            ->patch(route('error-logs.status', $errorLog), ['operation' => 'reopen'])
            ->assertRedirect();

        $this->assertSame(ErrorLogStatus::Open, $errorLog->refresh()->status);
        $this->assertNull($errorLog->resolved_by_id);
    }

    public function test_owner_cannot_view_or_update_another_agencys_error(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $errorLog = ErrorLog::factory()->for($otherAgency)->create();

        $this->actingAs($owner)->get(route('error-logs.show', $errorLog))->assertForbidden();
        $this->actingAs($owner)
            ->patch(route('error-logs.status', $errorLog), ['operation' => 'ignore', 'resolution_note' => 'Not ours'])
            ->assertForbidden();
    }

    public function test_filters_apply_to_status_severity_and_search(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        ErrorLog::factory()->for($agency)->create(['message' => 'WordPress bağlantısı koptu', 'status' => ErrorLogStatus::Open]);
        ErrorLog::factory()->for($agency)->create(['message' => 'Başka kayıt', 'status' => ErrorLogStatus::Resolved]);

        $this->actingAs($owner)
            ->get(route('error-logs.index', ['status' => 'open', 'q' => 'WordPress']))
            ->assertOk()
            ->assertSee('WordPress bağlantısı koptu')
            ->assertDontSee('Başka kayıt');
    }
}
