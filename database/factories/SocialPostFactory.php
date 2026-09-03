<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\SocialPost;
use App\Models\SocialPublishingAccount;
use App\SocialPostStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialPost> */
class SocialPostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'social_publishing_account_id' => function (array $attributes): int {
                return SocialPublishingAccount::factory()->create(['agency_id' => $attributes['agency_id']])->id;
            },
            'article_id' => null,
            'created_by' => null,
            'content' => fake()->sentence(12),
            'link_url' => fake()->url(),
            'media_url' => null,
            'scheduled_for' => null,
            'status' => SocialPostStatus::Draft,
            'external_id' => null,
            'error_message' => null,
            'published_at' => null,
        ];
    }
}
