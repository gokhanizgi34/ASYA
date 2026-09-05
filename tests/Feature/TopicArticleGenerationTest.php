<?php

namespace Tests\Feature;

use App\IntegrationProvider;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\Article;
use App\Models\PublishingTarget;
use App\Models\User;
use App\Models\VisualAsset;
use App\VisualSourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TopicArticleGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_generate_topic_article_with_uploaded_image_and_send_it_to_publication_center(): void
    {
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode($this->generatedNews(), JSON_UNESCAPED_UNICODE)]]],
        ])]);
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        ApiIntegration::factory()->ai(IntegrationProvider::OpenAi)->for($agency)->create([
            'base_url' => 'https://93.184.216.34/v1/models',
            'credential' => 'topic-key',
            'is_default' => true,
        ]);
        PublishingTarget::factory()->for($agency)->create(['is_active' => true]);

        $response = $this->actingAs($owner)->post(route('articles.generate-topic'), [
            'topic' => '2026 KYK yurt sonuçları ne zaman açıklanacak?',
            'image' => UploadedFile::fake()->image('kyk-duyuru.jpg', 1200, 800),
            'confirm_image_rights' => '1',
        ]);

        $article = Article::query()->where('agency_id', $agency->id)->firstOrFail();
        $response->assertRedirect(route('publications.index'));
        $this->assertSame('2026 KYK Yurt Sonuçları İçin Geri Sayım', $article->title);
        $this->assertDatabaseHas('visual_assets', [
            'article_id' => $article->id,
            'source_type' => VisualSourceType::Upload->value,
            'is_selected' => true,
        ]);
        $visual = VisualAsset::query()->where('article_id', $article->id)->firstOrFail();
        Storage::disk('public')->assertExists($visual->storage_path);
        $this->assertDatabaseHas('publications', ['article_id' => $article->id]);
        Queue::assertPushedOn('publishing', PublishArticleToWordPress::class);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/chat/completions')
            && str_contains((string) data_get($request->data(), 'messages.0.content.0.text'), '2026 KYK yurt sonuçları')
            && str_starts_with((string) data_get($request->data(), 'messages.0.content.1.image_url.url'), 'data:image/jpeg;base64,'));
    }

    public function test_editor_cannot_use_direct_topic_publication(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)->post(route('articles.generate-topic'), [
            'topic' => '2026 KYK yurt sonuçları ne zaman açıklanacak?',
        ])->assertForbidden();

        $this->assertDatabaseCount('articles', 0);
    }

    /** @return array<string, mixed> */
    private function generatedNews(): array
    {
        $paragraph = 'Gençlik ve Spor Bakanlığı tarafından yürütülen yurt yerleştirme sürecinde öğrencilerin resmî açıklamaları takip etmesi bekleniyor. Sonuç tarihi kesinleşmeden belirli bir gün açıklanmış gibi paylaşım yapılmaması önem taşıyor.';

        return [
            'title' => '2026 KYK Yurt Sonuçları İçin Geri Sayım',
            'summary' => 'KYK yurt yerleştirme sonuçlarının açıklanma süreci, sorgulama adımları ve öğrencilerin dikkat etmesi gereken noktalar.',
            'body' => implode("\n\n", array_fill(0, 6, $paragraph)),
            'focus_keyword' => '2026 KYK yurt sonuçları',
            'keywords' => ['KYK yurt sonuçları', 'GSB yurt başvurusu'],
            'hashtags' => ['#KYK', '#GSB'],
            'category' => 'Eğitim',
        ];
    }
}
