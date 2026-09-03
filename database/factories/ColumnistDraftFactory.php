<?php

namespace Database\Factories;

use App\ColumnistDraftStatus;
use App\Models\Agency;
use App\Models\AiColumnist;
use App\Models\ColumnistDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ColumnistDraft> */
class ColumnistDraftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'ai_columnist_id' => function (array $attributes): int {
                return AiColumnist::factory()->create(['agency_id' => $attributes['agency_id']])->id;
            },
            'created_by' => null,
            'reviewed_by' => null,
            'topic' => fake()->sentence(),
            'source_notes' => fake()->paragraphs(2, true),
            'headline' => fake()->sentence(),
            'body' => fake()->paragraphs(5, true),
            'prompt_snapshot' => ['mode' => 'local_editorial_preview'],
            'status' => ColumnistDraftStatus::Draft,
            'reviewed_at' => null,
        ];
    }
}
