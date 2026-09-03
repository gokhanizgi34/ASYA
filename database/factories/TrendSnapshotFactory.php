<?php

namespace Database\Factories;

use App\Models\TrendSnapshot;
use App\Models\TrendTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrendSnapshot> */
class TrendSnapshotFactory extends Factory
{
    public function definition(): array
    {
        $periodEnd = now()->startOfMinute();

        return ['trend_topic_id' => TrendTopic::factory(), 'mention_count' => 12, 'source_count' => 4, 'score' => 95, 'velocity' => 120, 'period_start' => $periodEnd->copy()->subMinutes(15), 'period_end' => $periodEnd];
    }
}
