<?php

namespace Database\Factories;

use App\HttpMethod;
use App\Models\Agency;
use App\Models\LearnedRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LearnedRoute> */
class LearnedRouteFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'publishing_target_id' => null,
            'host' => fake()->domainName(),
            'path_pattern' => '/wp-json/wp/v2/posts',
            'method' => HttpMethod::Post,
            'purpose' => 'WordPress yazısı oluşturma',
            'successful_count' => 8,
            'failed_count' => 2,
            'confidence' => 80,
            'last_status_code' => 201,
            'is_enabled' => true,
            'first_observed_at' => now()->subDays(7),
            'last_observed_at' => now(),
            'last_success_at' => now(),
        ];
    }
}
