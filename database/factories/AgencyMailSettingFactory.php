<?php

namespace Database\Factories;

use App\MailTransportScheme;
use App\Models\Agency;
use App\Models\AgencyMailSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgencyMailSetting> */
class AgencyMailSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'updated_by' => null,
            'host' => 'smtp.example.com',
            'port' => 587,
            'scheme' => MailTransportScheme::Smtp,
            'username' => fake()->userName(),
            'password' => 'smtp-secret-password',
            'from_address' => fake()->safeEmail(),
            'from_name' => 'ASYA Bildirim',
            'notification_email' => fake()->safeEmail(),
            'is_active' => true,
            'last_tested_at' => null,
            'last_error' => null,
        ];
    }
}
