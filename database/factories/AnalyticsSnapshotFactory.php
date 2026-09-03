<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\AnalyticsSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnalyticsSnapshot> */
class AnalyticsSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return ['agency_id' => Agency::factory(), 'report_date' => fake()->unique()->dateTimeBetween('-30 days')->format('Y-m-d'), 'raw_news_count' => 50, 'articles_created_count' => 20, 'articles_published_count' => 15, 'publication_success_count' => 14, 'publication_failure_count' => 1, 'campaigns_created_count' => 2, 'campaign_contents_count' => 8, 'trend_topics_count' => 5, 'seo_word_count' => 9000, 'average_seo_score' => 78.5, 'average_trend_score' => 95.2, 'details' => ['publication_success_rate' => 93.33], 'aggregated_at' => now()];
    }
}
