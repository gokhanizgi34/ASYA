<?php

namespace Tests\Feature;

use App\CampaignContentStatus;
use App\IntegrationProvider;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\CampaignContent;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\User;
use App\Services\AutomaticArticleVisualManager;
use App\Services\GeneratedContentPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PixabayVisualFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_pixabay_uses_relevant_image_for_news_without_source_visual(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/api/*' => Http::response([
                'hits' => [[
                    'id' => 42,
                    'tags' => 'pendik, sahil, etkinlik',
                    'pageURL' => 'https://pixabay.com/photos/pendik-42/',
                    'largeImageURL' => 'https://93.184.216.34/images/pixabay.png',
                    'imageWidth' => 1920,
                    'imageHeight' => 1080,
                    'likes' => 80,
                ]],
            ]),
            'https://93.184.216.34/images/pixabay.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        $agency = Agency::factory()->create();
        $article = Article::factory()->for($agency)->create([
            'title' => 'Pendik sahilinde yeni etkinlik başladı',
            'editorial_metadata' => ['content_type' => 'news', 'category' => 'Pendik'],
        ]);
        $this->createPixabayIntegration($agency);

        $visual = app(AutomaticArticleVisualManager::class)->ensure($article);

        $this->assertNotNull($visual);
        $this->assertSame('archive', $visual->source_type->value);
        Storage::disk('public')->assertExists($visual->storage_path);
    }

    public function test_pixabay_rejects_unrelated_animal_for_horoscope(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/api/*' => Http::response([
                'hits' => [[
                    'tags' => 'seal, animal, wildlife',
                    'largeImageURL' => 'https://93.184.216.34/images/seal.jpg',
                    'imageWidth' => 1920,
                    'imageHeight' => 1080,
                ]],
            ]),
        ]);
        $agency = Agency::factory()->create();
        $article = Article::factory()->for($agency)->create([
            'title' => 'Günlük burç yorumları',
            'editorial_metadata' => ['content_type' => 'horoscope', 'category' => 'Burçlar'],
        ]);
        $this->createPixabayIntegration($agency);

        $visual = app(AutomaticArticleVisualManager::class)->ensure($article);

        $this->assertNull($visual);
        Http::assertSentCount(1);
    }

    public function test_generated_horoscope_reaches_publication_center_with_pixabay_media(): void
    {
        Storage::fake('public');
        Queue::fake();
        Http::preventStrayRequests();
        $this->fakePixabay();

        $agency = Agency::factory()->create();
        $creator = User::factory()->agencyOwner()->for($agency)->create();
        PublishingTarget::factory()->for($agency)->create();
        $this->createPixabayIntegration($agency);

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

        $publication = Publication::query()->where('article_id', $article->id)->firstOrFail();

        $this->assertNotNull(data_get($publication->payload, 'media.path'));
        Storage::disk('public')->assertExists((string) data_get($publication->payload, 'media.path'));
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://93.184.216.34/api/')
            && str_contains((string) $request['q'], 'zodiac'));
        Queue::assertPushed(PublishArticleToWordPress::class);
    }

    public function test_linked_campaign_article_uses_relevant_pixabay_visual_when_approved(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/api/*' => Http::response([
                'hits' => [[
                    'tags' => 'sonbahar, kampanya, etkinlik',
                    'pageURL' => 'https://pixabay.com/photos/campaign-42/',
                    'largeImageURL' => 'https://93.184.216.34/images/pixabay.png',
                    'imageWidth' => 1920,
                    'imageHeight' => 1080,
                ]],
            ]),
            'https://93.184.216.34/images/pixabay.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->create([
            'author_id' => $owner->id,
            'title' => 'Sonbahar kampanyası başladı',
            'editorial_metadata' => ['content_type' => 'campaign', 'category' => 'Kampanyalar'],
        ]);
        $campaign = Campaign::factory()->for($agency)->for($owner, 'owner')->create();
        $content = CampaignContent::factory()
            ->for($campaign)
            ->for($article)
            ->for($owner, 'creator')
            ->create();
        $this->createPixabayIntegration($agency);

        $this->actingAs($owner)
            ->patch(route('campaign-contents.status', [$campaign, $content]), [
                'status' => CampaignContentStatus::Approved->value,
            ])
            ->assertRedirect();

        $this->assertSame(CampaignContentStatus::Approved, $content->fresh()->status);
        $this->assertNotNull($article->fresh()->selectedVisualAsset);
        Http::assertSentCount(2);
    }

    private function createPixabayIntegration(Agency $agency): ApiIntegration
    {
        return ApiIntegration::factory()->for($agency)->create([
            'provider' => IntegrationProvider::Pixabay,
            'name' => 'Pixabay',
            'base_url' => 'https://93.184.216.34/api/',
            'credential' => 'pixabay-key',
            'visual_enabled' => true,
            'is_active' => true,
        ]);
    }

    private function fakePixabay(): void
    {
        Http::fake([
            'https://93.184.216.34/api/*' => Http::response([
                'hits' => [[
                    'id' => 42,
                    'tags' => 'zodiac, astrology, stars',
                    'pageURL' => 'https://pixabay.com/photos/zodiac-42/',
                    'largeImageURL' => 'https://93.184.216.34/images/pixabay.png',
                    'imageWidth' => 1920,
                    'imageHeight' => 1080,
                    'likes' => 80,
                ]],
            ]),
            'https://93.184.216.34/images/pixabay.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);
    }

    private function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
