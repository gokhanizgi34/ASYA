<?php

namespace Database\Factories;

use App\ArticleStatus;
use App\Models\Agency;
use App\Models\Article;
use App\SourceTrustStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(7);

        return [
            'agency_id' => Agency::factory(),
            'author_id' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => fake()->paragraph(),
            'body' => fake()->paragraphs(5, true),
            'status' => ArticleStatus::Draft,
            'source_trust_status' => SourceTrustStatus::Unverified,
            'source_name' => fake()->company(),
            'source_url' => fake()->url(),
            'published_at' => null,
            'failure_message' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::PendingApproval,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Failed,
            'failure_message' => 'İçerik işleme sırasında hata oluştu.',
        ]);
    }
}
