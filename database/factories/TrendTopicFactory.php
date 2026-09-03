<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\TrendTopic;
use App\TrendStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TrendTopic> */
class TrendTopicFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return ['agency_id' => Agency::factory(), 'name' => Str::title($name), 'normalized_name' => Str::slug($name, ' '), 'status' => TrendStatus::Rising, 'mention_count' => 12, 'source_count' => 4, 'score' => 95, 'velocity' => 120, 'context' => ['examples' => [], 'sources' => []], 'first_seen_at' => now()->subHours(12), 'last_seen_at' => now(), 'analyzed_at' => now()];
    }
}
