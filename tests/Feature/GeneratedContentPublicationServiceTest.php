<?php

namespace Tests\Feature;

use App\ArticleStatus;
use App\Models\Agency;
use App\Models\Article;
use App\Models\PublishingTarget;
use App\Models\User;
use App\Services\GeneratedContentPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class GeneratedContentPublicationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generated_content_without_relevant_visual_is_not_sent_to_publication_center(): void
    {
        Queue::fake();
        $agency = Agency::factory()->create();
        $creator = User::factory()->agencyOwner()->for($agency)->create();
        PublishingTarget::factory()->for($agency)->create();

        try {
            app(GeneratedContentPublicationService::class)->send($agency->id, $creator, [
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
            $this->fail('Uygun görseli olmayan içerik yayınlanmamalıydı.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('görsel bekliyor', $exception->getMessage());
        }

        $article = Article::query()->firstOrFail();
        $this->assertSame(ArticleStatus::Failed, $article->status);
        $this->assertStringContainsString('Görsel bekliyor', (string) $article->failure_message);
        $this->assertDatabaseCount('publications', 0);
        Queue::assertNothingPushed();
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
