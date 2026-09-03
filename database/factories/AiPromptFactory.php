<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\AiPrompt;
use App\PromptTone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPrompt>
 */
class AiPromptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'name' => fake()->unique()->words(3, true),
            'category' => fake()->randomElement(['haber', 'seo', 'sosyal_medya', 'kose_yazisi']),
            'tone' => fake()->randomElement(PromptTone::cases()),
            'language' => 'tr',
            'target_length' => 600,
            'temperature' => 0.70,
            'system_prompt' => 'Deneyimli bir Türkçe haber editörü gibi davran.',
            'user_prompt_template' => 'Aşağıdaki içeriği özgün bir haber olarak yaz: {content}',
            'is_active' => true,
            'version' => 1,
            'last_tested_at' => null,
        ];
    }

    public function global(): static
    {
        return $this->state(fn (array $attributes): array => [
            'agency_id' => null,
        ]);
    }
}
