<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\User;
use App\PublicationStatus;
use App\RemotePublicationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Publication> */
class PublicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => fn (array $attributes) => PublishingTarget::query()->findOrFail($attributes['publishing_target_id'])->agency_id,
            'article_id' => Article::factory(),
            'publishing_target_id' => PublishingTarget::factory(),
            'created_by' => User::factory(),
            'status' => PublicationStatus::Queued,
            'remote_status' => RemotePublicationStatus::Draft,
            'remote_post_id' => null,
            'remote_media_id' => null,
            'remote_url' => null,
            'payload' => [
                'title' => 'Test haberi',
                'slug' => 'test-haberi',
                'content' => "Birinci paragraf.\n\nİkinci paragraf.",
                'excerpt' => 'Test özeti',
                'author' => 1,
                'categories' => [1],
                'tags' => [],
                'meta' => ['asya_focus_keyword' => 'test'],
                'media' => ['disk' => 'public', 'path' => 'visuals/test.jpg', 'title' => 'Test görseli', 'alt_text' => 'Test görseli'],
            ],
            'response_meta' => null,
            'attempt_count' => 0,
            'failure_message' => null,
            'queued_at' => now(),
            'started_at' => null,
            'published_at' => null,
            'completed_at' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => PublicationStatus::Failed, 'failure_message' => 'Uzak sunucu hatası.', 'completed_at' => now()]);
    }
}
