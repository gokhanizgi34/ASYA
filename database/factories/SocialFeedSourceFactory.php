<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\SocialFeedSource;
use App\Models\SocialListeningWatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialFeedSource> */
class SocialFeedSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'social_listening_watch_id' => function (array $attributes): int {
                return SocialListeningWatch::factory()->create(['agency_id' => $attributes['agency_id']])->id;
            },
            'created_by' => null,
            'name' => fake()->unique()->words(3, true),
            'platform' => 'x',
            'source_type' => 'json_manual',
            'endpoint_url' => null,
            'auth_secret' => null,
            'field_map' => [
                'external_id' => 'id',
                'content' => 'text',
                'author_handle' => 'author',
                'url' => 'url',
                'published_at' => 'published_at',
                'engagement_count' => 'engagement',
            ],
            'is_active' => true,
            'last_imported_at' => null,
        ];
    }
}
