<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\TranslationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArticleTranslation> */
class ArticleTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'article_id' => function (array $attributes): int {
                return Article::factory()->create(['agency_id' => $attributes['agency_id']])->id;
            },
            'created_by' => null,
            'reviewed_by' => null,
            'source_locale' => 'tr',
            'target_locale' => fake()->randomElement(['en', 'de', 'fr', 'ar']),
            'source_checksum' => hash('sha256', fake()->text()),
            'title' => fake()->sentence(),
            'summary' => fake()->paragraph(),
            'body' => fake()->paragraphs(5, true),
            'glossary' => [],
            'status' => TranslationStatus::Draft,
            'reviewed_at' => null,
        ];
    }
}
