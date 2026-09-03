<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\SeoAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoAnalysis>
 */
class SeoAnalysisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'agency_id' => fn (array $attributes) => Article::query()->findOrFail($attributes['article_id'])->agency_id,
            'focus_keyword' => 'gündem haberi',
            'meta_title' => fake()->sentence(6),
            'meta_description' => fake()->text(155),
            'keywords' => ['gündem', 'haber', 'Türkiye'],
            'hashtags' => ['#Gündem', '#Haber'],
            'score' => 75,
            'readability_score' => 80,
            'word_count' => 450,
            'keyword_density' => 1.5,
            'issues' => [],
            'recommendations' => ['Alt başlık ekleyin.'],
            'analyzed_at' => now(),
        ];
    }
}
