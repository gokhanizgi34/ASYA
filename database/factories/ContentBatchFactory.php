<?php

namespace Database\Factories;

use App\ContentBatchStatus;
use App\Models\Agency;
use App\Models\AiPrompt;
use App\Models\ContentBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentBatch> */
class ContentBatchFactory extends Factory
{
    public function definition(): array
    {
        $agency = Agency::factory();

        return [
            'agency_id' => $agency,
            'created_by' => null,
            'ai_prompt_id' => AiPrompt::factory()->global(),
            'name' => fake()->words(3, true),
            'status' => ContentBatchStatus::Queued,
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
            'settings' => ['prompt_snapshot' => ['name' => 'Test Prompt', 'version' => 1, 'tone' => 'neutral', 'target_length' => 600]],
            'failure_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContentBatchStatus::Completed,
            'total_items' => 1,
            'processed_items' => 1,
            'completed_at' => now(),
        ]);
    }
}
