<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\RawNewsItem;
use App\RawNewsStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RawNewsItem>
 */
class RawNewsItemFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(8);
        $sourceUrl = fake()->unique()->url();

        return [
            'agency_id' => Agency::factory(),
            'external_id' => fake()->unique()->uuid(),
            'source_name' => fake()->company(),
            'source_url' => $sourceUrl,
            'original_title' => $title,
            'original_body' => fake()->paragraphs(6, true),
            'original_image_url' => fake()->imageUrl(1280, 720),
            'language' => 'tr',
            'status' => RawNewsStatus::Pending,
            'priority' => 50,
            'checksum' => hash('sha256', $sourceUrl.'|'.$title),
            'discovered_at' => now(),
            'expires_at' => now()->addDays(2),
            'processed_at' => null,
            'failure_message' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RawNewsStatus::Failed,
            'failure_message' => 'Ham içerik işlenemedi.',
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RawNewsStatus::Processed,
            'processed_at' => now(),
        ]);
    }
}
