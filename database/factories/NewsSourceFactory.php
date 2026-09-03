<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\NewsSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NewsSource> */
class NewsSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'created_by' => null,
            'name' => fake()->unique()->company(),
            'domain' => fake()->unique()->domainName(),
            'feed_url' => 'https://'.fake()->domainName().'/feed.xml',
            'feed_format' => 'auto',
            'source_type' => 'news_site',
            'notes' => fake()->sentence(),
            'is_active' => true,
            'daily_item_limit' => 10,
            'latest_score' => null,
            'latest_band' => null,
            'last_assessed_at' => null,
            'last_fetched_at' => null,
            'last_status_code' => null,
            'last_item_count' => 0,
            'last_fetch_error' => null,
            'last_ingestion_method' => null,
            'last_content_fingerprint' => null,
            'last_change_detected_at' => null,
            'last_crawled_pages' => 0,
        ];
    }
}
