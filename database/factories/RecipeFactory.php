<?php

namespace Database\Factories;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category' => 'main',
            'title' => fake()->unique()->sentence(3),
            'ingredients' => fake()->sentences(4, true),
            'instructions' => fake()->paragraphs(2, true),
            'is_active' => true,
            'last_selected_at' => null,
        ];
    }
}
