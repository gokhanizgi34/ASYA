<?php

namespace Database\Factories;

use App\CopyrightStatus;
use App\Models\Agency;
use App\Models\VisualAsset;
use App\VisualAssetStatus;
use App\VisualSourceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VisualAsset> */
class VisualAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'article_id' => null,
            'uploaded_by' => null,
            'title' => fake()->sentence(4),
            'source_type' => VisualSourceType::Archive,
            'status' => VisualAssetStatus::Approved,
            'copyright_status' => CopyrightStatus::Licensed,
            'source_url' => fake()->imageUrl(1280, 720),
            'storage_disk' => 'public',
            'storage_path' => null,
            'mime_type' => 'image/jpeg',
            'width' => 1280,
            'height' => 720,
            'quality_score' => 100,
            'alt_text' => fake()->sentence(),
            'headline_overlay' => null,
            'generation_prompt' => null,
            'evaluation_notes' => 'Görsel yayın kalite ve telif eşiklerini karşılıyor.',
            'failure_message' => null,
            'is_selected' => false,
            'generated_at' => null,
            'evaluated_at' => now(),
        ];
    }

    public function needsReplacement(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VisualAssetStatus::NeedsReplacement,
            'copyright_status' => CopyrightStatus::Restricted,
            'quality_score' => 10,
        ]);
    }

    public function generating(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source_type' => VisualSourceType::AiGenerated,
            'status' => VisualAssetStatus::Generating,
            'copyright_status' => CopyrightStatus::Original,
            'source_url' => null,
            'width' => null,
            'height' => null,
            'quality_score' => 0,
            'generation_prompt' => 'Habere özel fotogerçekçi yatay kapak görseli.',
            'evaluated_at' => null,
        ]);
    }
}
