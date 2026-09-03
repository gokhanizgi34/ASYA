<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettingRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_editor_is_forbidden(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->get(route('system-settings.index'))->assertRedirect(route('login'));
        $this->actingAs($editor)->get(route('system-settings.index'))->assertForbidden();
        $this->actingAs($editor)->put(route('system-settings.update'), ['settings' => $this->validSettings()])->assertForbidden();
    }

    public function test_system_administrator_can_save_global_settings(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $settings = $this->validSettings();
        $settings['app_display_name'] = 'ASYA Haber Merkezi';
        $settings['publishing_retry_count'] = 5;

        $this->actingAs($administrator)
            ->put(route('system-settings.update'), ['settings' => $settings])
            ->assertRedirect(route('system-settings.index'));

        $this->assertDatabaseHas('system_settings', [
            'scope_key' => 'system',
            'agency_id' => null,
            'key' => 'app.display_name',
            'value' => 'ASYA Haber Merkezi',
            'updated_by_id' => $administrator->id,
        ]);
        $this->assertDatabaseHas('system_settings', [
            'scope_key' => 'system',
            'key' => 'publishing.retry_count',
            'value' => '5',
        ]);
    }

    public function test_owner_is_forced_to_own_agency_even_when_spoofing_scope(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $settings = $this->validSettings();
        $settings['app_display_name'] = 'Kendi Ajansım';

        $this->actingAs($owner)->put(route('system-settings.update'), [
            'agency_id' => $otherAgency->id,
            'settings' => $settings,
        ])->assertRedirect(route('system-settings.index', ['agency_id' => $agency->id]));

        $this->assertDatabaseHas('system_settings', [
            'scope_key' => 'agency:'.$agency->id,
            'key' => 'app.display_name',
            'value' => 'Kendi Ajansım',
        ]);
        $this->assertDatabaseMissing('system_settings', ['scope_key' => 'agency:'.$otherAgency->id]);
    }

    public function test_owner_sees_only_own_agency_in_scope_selector(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)
            ->get(route('system-settings.index', ['agency_id' => $otherAgency->id]))
            ->assertOk()
            ->assertSee($agency->name)
            ->assertDontSee($otherAgency->name)
            ->assertDontSee('Sistem varsayılanları');
    }

    public function test_invalid_setting_values_are_rejected_without_writes(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $settings = $this->validSettings();
        $settings['publishing_retry_count'] = 99;
        $settings['app_timezone'] = 'Invalid/Timezone';

        $this->actingAs($administrator)
            ->put(route('system-settings.update'), ['settings' => $settings])
            ->assertSessionHasErrors(['settings.publishing_retry_count', 'settings.app_timezone']);

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_inherit_flag_removes_existing_agency_override(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        SystemSetting::factory()->for($agency)->create();
        $settings = $this->validSettings();

        $this->actingAs($owner)->put(route('system-settings.update'), [
            'settings' => $settings,
            'inherit' => ['app_display_name' => true],
        ])->assertRedirect();

        $this->assertDatabaseMissing('system_settings', [
            'scope_key' => 'agency:'.$agency->id,
            'key' => 'app.display_name',
        ]);
    }

    public function test_agency_display_name_is_applied_to_authenticated_pages(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        SystemSetting::factory()->for($agency)->create([
            'key' => 'app.display_name',
            'value' => 'Asya Ege Haber',
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Asya Ege Haber');
    }

    /** @return array<string, string|int|bool> */
    private function validSettings(): array
    {
        return collect(app(SystemSettingRegistry::class)->definitions())
            ->mapWithKeys(fn (array $definition): array => [$definition['field'] => $definition['default']])
            ->all();
    }
}
