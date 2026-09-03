<?php

namespace Database\Factories;

use App\ErrorSeverity;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SystemNotification> */
class SystemNotificationFactory extends Factory
{
    public function definition(): array
    {
        $fingerprint = Str::uuid()->toString();

        return [
            'agency_id' => null,
            'recipient_user_id' => User::factory(),
            'type' => 'system',
            'severity' => ErrorSeverity::Warning,
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(),
            'action_route' => null,
            'action_parameters' => null,
            'fingerprint' => hash('sha256', $fingerprint),
            'occurrences' => 1,
            'first_occurred_at' => now(),
            'last_occurred_at' => now(),
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}
