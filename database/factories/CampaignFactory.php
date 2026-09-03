<?php

namespace Database\Factories;

use App\CampaignChannel;
use App\CampaignStatus;
use App\Models\Agency;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Campaign> */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return ['agency_id' => Agency::factory(), 'owner_id' => User::factory(), 'name' => fake()->unique()->sentence(3), 'status' => CampaignStatus::Draft, 'objective' => fake()->paragraph(), 'target_audience' => fake()->sentence(), 'channels' => [CampaignChannel::Website->value], 'brief' => fake()->paragraph(), 'kpis' => ['reach' => 10000], 'budget' => 5000, 'starts_at' => now()->addDay(), 'ends_at' => now()->addWeek()];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => CampaignStatus::Active]);
    }
}
