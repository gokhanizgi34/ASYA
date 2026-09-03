<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\SocialListeningWatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialListeningWatch> */
class SocialListeningWatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'created_by' => null,
            'name' => fake()->unique()->words(3, true),
            'keywords' => ['ASYA', 'haber'],
            'excluded_terms' => [],
            'platforms' => ['x', 'instagram'],
            'alert_threshold' => 70,
            'is_active' => true,
        ];
    }
}
