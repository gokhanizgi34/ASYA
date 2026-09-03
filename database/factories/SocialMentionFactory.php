<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\SocialListeningWatch;
use App\Models\SocialMention;
use App\SocialMentionStatus;
use App\SocialSentiment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialMention> */
class SocialMentionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'social_listening_watch_id' => function (array $attributes): int {
                return SocialListeningWatch::factory()->create(['agency_id' => $attributes['agency_id']])->id;
            },
            'created_by' => null,
            'platform' => fake()->randomElement(['x', 'instagram', 'facebook', 'youtube']),
            'external_id' => fake()->unique()->uuid(),
            'author_handle' => '@'.fake()->userName(),
            'url' => fake()->url(),
            'title' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'published_at' => now(),
            'engagement_count' => fake()->numberBetween(0, 1000),
            'sentiment' => SocialSentiment::Neutral,
            'sentiment_score' => 0,
            'urgency_score' => 20,
            'matched_keywords' => ['ASYA'],
            'status' => SocialMentionStatus::New,
        ];
    }
}
