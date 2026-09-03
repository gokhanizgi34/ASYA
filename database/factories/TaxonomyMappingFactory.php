<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\PublishingTarget;
use App\Models\TaxonomyMapping;
use App\TaxonomyType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TaxonomyMapping> */
class TaxonomyMappingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $term = fake()->unique()->word();

        return [
            'agency_id' => Agency::factory(),
            'publishing_target_id' => function (array $attributes): int {
                return PublishingTarget::factory()->create(['agency_id' => $attributes['agency_id']])->id;
            },
            'type' => TaxonomyType::Category,
            'source_term' => $term,
            'source_key' => Str::slug($term),
            'remote_id' => fake()->numberBetween(1, 500),
            'remote_name' => Str::title($term),
            'priority' => 50,
            'is_active' => true,
        ];
    }
}
