<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\SocialFeedImport;
use App\Models\SocialFeedSource;
use App\SocialFeedImportStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialFeedImport> */
class SocialFeedImportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'social_feed_source_id' => function (array $attributes): int {
                return SocialFeedSource::factory()->create(['agency_id' => $attributes['agency_id']])->id;
            },
            'started_by' => null,
            'status' => SocialFeedImportStatus::Completed,
            'received_count' => 1,
            'imported_count' => 1,
            'skipped_count' => 0,
            'failed_count' => 0,
            'errors' => [],
            'started_at' => now(),
            'completed_at' => now(),
        ];
    }
}
