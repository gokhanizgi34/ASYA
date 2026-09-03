<?php

namespace Database\Factories;

use App\ErrorLogStatus;
use App\ErrorSeverity;
use App\Models\Agency;
use App\Models\ErrorLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ErrorLog> */
class ErrorLogFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $exceptionClass = 'RuntimeException';
        $message = fake()->sentence();

        return [
            'agency_id' => Agency::factory(),
            'user_id' => null,
            'scope_key' => fn (array $attributes): string => 'agency:'.$attributes['agency_id'],
            'fingerprint' => fake()->unique()->sha256(),
            'severity' => ErrorSeverity::Error,
            'status' => ErrorLogStatus::Open,
            'exception_class' => $exceptionClass,
            'message' => $message,
            'file' => 'app/Services/ExampleService.php',
            'line' => 42,
            'http_method' => 'GET',
            'path' => 'panel',
            'route_name' => 'dashboard',
            'occurrences' => 1,
            'context' => ['environment' => 'testing'],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }

    public function system(): static
    {
        return $this->state(fn (): array => ['agency_id' => null, 'scope_key' => 'system']);
    }
}
