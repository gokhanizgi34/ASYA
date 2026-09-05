<?php

namespace Tests\Feature;

use App\ArticleStatus;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\User;
use App\Services\GeneratedContentPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GeneratedContentPublicationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_only_generated_content_is_sent_to_publication_center_without_visual(): void
    {
        Queue::fake();
        $agency = Agency::factory()->create();
        $creator = User::factory()->agencyOwner()->for($agency)->create();
        PublishingTarget::factory()->for($agency)->create();

        $article = app(GeneratedContentPublicationService::class)->send($agency->id, $creator, [
            'title' => 'Günlük burç yorumları',
            'summary' => 'Burçlara ilişkin günlük değerlendirmeler hazırlandı.',
            'body' => 'Koç burcu için günün değerlendirmesi. Boğa burcu için günün değerlendirmesi.',
            'keywords' => ['günlük burç yorumları'],
            'hashtags' => ['#Burçlar'],
            'category' => 'Burçlar',
            'source_type' => 'horoscope',
            'source_id' => '2026-09-04',
            'destination' => 'publish',
        ]);

        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertDatabaseHas('seo_analyses', ['article_id' => $article->id]);
        $this->assertDatabaseHas('publications', ['article_id' => $article->id]);
        $publication = Publication::query()->firstOrFail();
        $this->assertNull(data_get($publication->payload, 'media'));
        Queue::assertPushed(PublishArticleToWordPress::class);
    }

    public function test_draft_destination_does_not_create_publication(): void
    {
        Queue::fake();
        $agency = Agency::factory()->create();
        $creator = User::factory()->agencyOwner()->for($agency)->create();
        PublishingTarget::factory()->for($agency)->create();

        $article = app(GeneratedContentPublicationService::class)->send($agency->id, $creator, [
            'title' => 'Taslak köşe yazısı',
            'summary' => 'Editoryal değerlendirme için taslak.',
            'body' => 'Bu metin yayın öncesinde editörün değerlendirmesine bırakılmıştır.',
            'category' => 'Köşe Yazıları',
            'source_type' => 'column',
            'source_id' => 42,
            'destination' => 'draft',
        ]);

        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->assertDatabaseCount('publications', 0);
        Queue::assertNothingPushed();
    }
}
