<?php

namespace Tests\Feature;

use App\ArticleStatus;
use App\IntegrationProvider;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\Article;
use App\Models\ContentBatch;
use App\Models\ContentBatchItem;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\RawNewsItem;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AutomaticArticlePublisher;
use App\Services\AutomaticArticleVisualManager;
use App\SettingValueType;
use App\SourceTrustStatus;
use App\VisualAssetStatus;
use App\VisualSourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AutomaticArticlePublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_is_completed_with_seo_source_image_taxonomy_and_wordpress_queue(): void
    {
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/images/source.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $target = PublishingTarget::factory()->for($agency)->create(['default_category_ids' => [12], 'default_tag_ids' => [34, 56]]);
        $article = Article::factory()->for($agency)->create([
            'author_id' => $owner->id,
            'title' => 'Pendik sahilinde yenileme çalışmaları başladı',
            'summary' => 'Pendik Belediyesi, Doğu Mahallesi sahilindeki yürüyüş yolu ve çocuk parkı yenileme çalışmalarını başlattı.',
            'body' => $this->generatedBody(),
            'source_name' => 'Pendik Belediyesi',
            'source_url' => 'https://www.pendik.bel.tr/haber/sahil-yenileme',
        ]);
        $rawNewsItem = RawNewsItem::factory()->for($agency)->create([
            'source_name' => 'Pendik Belediyesi',
            'source_url' => 'https://www.pendik.bel.tr/haber/sahil-yenileme',
            'original_title' => 'Pendik sahilinde yürüyüş yolu ve çocuk parkı yenileniyor',
            'original_body' => $this->sourceBody(),
            'original_image_url' => 'https://93.184.216.34/images/source.png',
        ]);
        ContentBatchItem::factory()->for(ContentBatch::factory()->for($agency)->create(['created_by' => $owner->id]))->for($rawNewsItem, 'rawNewsItem')->create(['article_id' => $article->id]);

        app(AutomaticArticlePublisher::class)->publish($article->id);

        $article->refresh();
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertSame(SourceTrustStatus::Verified, $article->source_trust_status);
        $this->assertNotNull($article->seoAnalysis);
        $visual = $article->selectedVisualAsset;
        $this->assertNotNull($visual);
        $this->assertSame(VisualAssetStatus::Approved, $visual->status);
        $this->assertSame(VisualSourceType::Original, $visual->source_type);
        Storage::disk('public')->assertExists($visual->storage_path);
        $publication = Publication::query()->where('article_id', $article->id)->where('publishing_target_id', $target->id)->firstOrFail();
        $this->assertSame([12], data_get($publication->payload, 'categories'));
        $this->assertContains('Pendik', data_get($publication->payload, 'taxonomy_names.categories'));
        $this->assertSame('Pendik', data_get($publication->payload, 'meta.asya_district_category'));
        $this->assertSame([34, 56], data_get($publication->payload, 'tags'));
        $this->assertNotEmpty(data_get($publication->payload, 'meta.asya_keywords'));
        $this->assertSame($visual->storage_path, data_get($publication->payload, 'media.path'));
        Queue::assertPushedOn('publishing', PublishArticleToWordPress::class, fn (PublishArticleToWordPress $job): bool => $job->publicationId === $publication->id);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://93.184.216.34/images/source.png');
    }

    public function test_missing_source_image_is_held_without_ai_request(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        $article = Article::factory()->create();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI görsel üretimi kapalıdır');
        try {
            app(AutomaticArticleVisualManager::class)->ensure($article);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_ai_image_is_generated_only_when_enabled_and_source_image_is_missing(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode($this->png())]],
            ]),
        ]);
        $agency = Agency::factory()->create();
        $article = Article::factory()->for($agency)->create();
        ApiIntegration::factory()->ai(IntegrationProvider::OpenAi)->for($agency)->create([
            'credential' => 'openai-key',
        ]);
        SystemSetting::factory()->for($agency)->create([
            'key' => 'visual.ai_generation_enabled',
            'value' => '1',
            'type' => SettingValueType::Boolean,
        ]);

        $visual = app(AutomaticArticleVisualManager::class)->ensure($article);

        $this->assertSame(VisualSourceType::AiGenerated, $visual->source_type);
        $this->assertTrue($visual->is_selected);
        Storage::disk('public')->assertExists($visual->storage_path);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/images/generations');
    }

    public function test_source_image_is_used_without_calling_ai_image_generation(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/images/source.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        $article = Article::factory()->create();
        $visual = app(AutomaticArticleVisualManager::class)->ensure($article, 'https://93.184.216.34/images/source.png');
        $this->assertSame(VisualSourceType::Original, $visual->source_type);
        $this->assertTrue($visual->is_selected);
        Storage::disk('public')->assertExists($visual->storage_path);
        Http::assertSentCount(1);
    }

    public function test_failed_source_image_download_does_not_fall_back_to_ai(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/images/missing.png' => Http::response('missing', 404)]);
        $article = Article::factory()->create();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI görsel üretimi kapalıdır');
        try {
            app(AutomaticArticleVisualManager::class)->ensure($article, 'https://93.184.216.34/images/missing.png');
        } finally {
            Http::assertSentCount(1);
        }
    }

    private function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }

    private function sourceBody(): string
    {
        return implode(' ', ['Pendik Belediyesi Doğu Mahallesi sahilinde yenileme çalışması başlattı.', 'Yürüyüş yolunun zemini ve aydınlatma direkleri proje kapsamında değiştirilecek.', 'Çocuk parkındaki eski oyun grupları güvenli ekipmanlarla yenilenecek.', 'Çalışmaların ekim ayının ikinci haftasında tamamlanması planlanıyor.', 'Sahil yolunun belirli bölümleri uygulama sırasında yaya kullanımına kapatılacak.', 'Vatandaşların yönlendirme levhalarını takip etmesi istendi.', 'Proje tamamlandığında yeni dinlenme alanları hizmete açılacak.']);
    }

    private function generatedBody(): string
    {
        return implode("\n\n", ['Pendik Belediyesi, Doğu Mahallesi sahilindeki yürüyüş yolu ile çocuk parkını kapsayan yenileme çalışmalarını başlattı. Ekipler proje alanında zemin sökümü yaptı.', 'Yürüyüş yolunun yıpranan zemini tamamen değiştirilecek. Sahil hattındaki aydınlatma direkleri de yeni sistemlerle yenilenecek.', 'Çocuk parkındaki eski oyun grupları kaldırılarak güvenlik standartlarına uygun ekipmanlar yerleştirilecek. Park zemini darbe emici malzemeyle kaplanacak.', 'Çalışmaların ekim ayının ikinci haftasında tamamlanması hedefleniyor. Uygulama takvimi sahadaki ilerlemeye göre sürdürülecek.', 'Sahil yolunun belirli bölümleri çalışma boyunca yaya kullanımına geçici olarak kapatılacak. Vatandaşlar alternatif geçişlere yönlendirilecek.', 'Yenileme tamamlandığında kıyı hattına yeni dinlenme alanları ve banklar eklenecek. Projenin aileler için güvenli kullanım sağlaması amaçlanıyor.', 'Ekipler açık bölümlerde günlük güvenlik kontrolü yapacak. Tamamlanan kısımlar kontrollerin ardından aşamalı olarak hizmete alınacak.']);
    }
}
