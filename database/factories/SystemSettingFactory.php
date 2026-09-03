<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\SystemSetting;
use App\SettingValueType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SystemSetting> */
class SystemSettingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'updated_by_id' => null,
            'scope_key' => fn (array $attributes): string => 'agency:'.$attributes['agency_id'],
            'key' => 'app.display_name',
            'value' => fake()->company(),
            'type' => SettingValueType::String,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (): array => ['agency_id' => null, 'scope_key' => 'system']);
    }
}
