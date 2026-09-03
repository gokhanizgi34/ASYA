<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\AiColumnist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AiColumnist> */
class AiColumnistFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->name();

        return ['agency_id' => Agency::factory(), 'ai_prompt_id' => null, 'created_by' => null, 'name' => $name, 'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999), 'pen_name' => $name, 'biography' => fake()->paragraph(), 'expertise' => ['gündem', 'toplum'], 'voice_guide' => fake()->paragraph(), 'disclosure' => 'Bu köşe yazısı yapay zekâ destekli hazırlanmış ve editoryal incelemeden geçirilmiştir.', 'is_active' => true];
    }
}
