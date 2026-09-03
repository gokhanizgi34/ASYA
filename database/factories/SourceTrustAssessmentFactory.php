<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\NewsSource;
use App\Models\SourceTrustAssessment;
use App\SourceTrustBand;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SourceTrustAssessment> */
class SourceTrustAssessmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'news_source_id' => function (array $attributes): int {
                return NewsSource::factory()->create(['agency_id' => $attributes['agency_id']])->id;
            },
            'assessed_by' => null,
            'identity_transparency' => 70,
            'evidence_quality' => 70,
            'correction_policy' => 70,
            'historical_accuracy' => 70,
            'editorial_independence' => 70,
            'weighted_score' => 70,
            'trust_band' => SourceTrustBand::Medium,
            'notes' => fake()->paragraph(),
            'assessed_at' => now(),
        ];
    }
}
