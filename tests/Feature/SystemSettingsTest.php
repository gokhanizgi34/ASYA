<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettings;
use App\SettingValueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_defaults_system_values_and_tenant_overrides(): void
    {
        $agency = Agency::factory()->create();
        $service = app(SystemSettings::class);

        $this->assertSame('ASYA', $service->get('app.display_name', $agency->id));

        SystemSetting::factory()->system()->create([
            'key' => 'app.display_name',
            'value' => 'Global Haber',
            'type' => SettingValueType::String,
        ]);
        SystemSetting::factory()->for($agency)->create([
            'key' => 'app.display_name',
            'value' => 'Ajans Haber',
            'type' => SettingValueType::String,
        ]);

        $freshService = app()->make(SystemSettings::class);
        $this->assertSame('Global Haber', $freshService->get('app.display_name'));
        $this->assertSame('Ajans Haber', $freshService->get('app.display_name', $agency->id));
    }

    public function test_save_can_remove_tenant_override_to_restore_inheritance(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        SystemSetting::factory()->for($agency)->create([
            'key' => 'publishing.retry_count',
            'value' => '8',
            'type' => SettingValueType::Integer,
        ]);
        $service = app(SystemSettings::class);

        $service->save(
            $agency->id,
            ['publishing_retry_count' => 4],
            ['publishing_retry_count' => true],
            $owner,
        );

        $this->assertDatabaseMissing('system_settings', [
            'scope_key' => 'agency:'.$agency->id,
            'key' => 'publishing.retry_count',
        ]);
        $this->assertSame(3, $service->get('publishing.retry_count', $agency->id));
    }

    public function test_boolean_and_integer_values_are_returned_with_correct_types(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $service = app(SystemSettings::class);

        $service->save(null, [
            'publishing_require_approval' => false,
            'analytics_retention_days' => 730,
        ], [], $administrator);

        $this->assertFalse($service->get('publishing.require_approval'));
        $this->assertSame(730, $service->get('analytics.retention_days'));
    }

    public function test_unknown_setting_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SystemSettings::class)->get('unknown.setting');
    }
}
