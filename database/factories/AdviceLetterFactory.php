<?php

namespace Database\Factories;

use App\AdviceLetterStatus;
use App\AdviceRiskLevel;
use App\Models\AdviceLetter;
use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdviceLetter> */
class AdviceLetterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'submitted_by' => null,
            'answered_by' => null,
            'pseudonym' => fake()->userName(),
            'category' => 'personal',
            'question' => fake()->paragraph(4),
            'status' => AdviceLetterStatus::Pending,
            'risk_level' => AdviceRiskLevel::Low,
            'risk_flags' => [],
            'publication_consent' => true,
            'response_title' => null,
            'response_body' => null,
            'answered_at' => null,
            'published_at' => null,
        ];
    }

    public function answered(): static
    {
        return $this->state(fn (): array => [
            'status' => AdviceLetterStatus::Answered,
            'response_title' => fake()->sentence(),
            'response_body' => fake()->paragraph(5),
            'answered_at' => now(),
        ]);
    }
}
