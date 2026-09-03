<?php

namespace Database\Factories;

use App\CampaignChannel;
use App\CampaignContentStatus;
use App\Models\Campaign;
use App\Models\CampaignContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CampaignContent> */
class CampaignContentFactory extends Factory
{
    public function definition(): array
    {
        return ['campaign_id' => Campaign::factory(), 'article_id' => null, 'created_by' => User::factory(), 'channel' => CampaignChannel::Website, 'status' => CampaignContentStatus::Draft, 'title' => fake()->sentence(), 'body' => fake()->paragraph(), 'call_to_action' => 'Haberi oku', 'destination_url' => fake()->url(), 'metadata' => [], 'approved_at' => null, 'published_at' => null];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => CampaignContentStatus::Approved, 'approved_at' => now()]);
    }
}
