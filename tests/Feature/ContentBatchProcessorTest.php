<?php

namespace Tests\Feature;

use App\ContentBatchItemStatus;
use App\ContentBatchStatus;
use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\Jobs\FinalizeAutomaticArticle;
use App\Jobs\ProcessContentBatch;
use App\Models\Agency;
use App\Models\AiPrompt;
use App\Models\ApiIntegration;
use App\Models\ContentBatch;
use App\Models\ContentBatchItem;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use App\Services\ContentBatchProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContentBatchProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_processor_creates_drafts_and_completes_batch_idempotently(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $prompt = AiPrompt::factory()->global()->create();
        $rawNewsItems = collect([
            RawNewsItem::factory()->for($agency)->create(['original_title' => 'Pendik sahilinde yenileme çalışması başladı', 'original_body' => $this->sourceBody()]),
            RawNewsItem::factory()->for($agency)->create(['original_title' => 'Kartal meydanında ulaşım düzenlemesi başladı', 'original_body' => $this->sourceBody()]),
        ]);
        $batch = $this->batch($agency, $editor, $prompt, $rawNewsItems->all());

        app(ContentBatchProcessor::class)->process($batch->id);
        app(ContentBatchProcessor::class)->process($batch->id);

        $batch->refresh();
        $this->assertSame(ContentBatchStatus::Completed, $batch->status);
        $this->assertSame(2, $batch->processed_items);
        $this->assertSame(0, $batch->failed_items);
        $this->assertDatabaseCount('articles', 2);
        $this->assertSame(2, RawNewsItem::query()->where('status', RawNewsStatus::Processed)->count());
        $this->assertSame(2, ContentBatchItem::query()->where('status', ContentBatchItemStatus::Completed)->count());
    }

    public function test_processor_marks_partial_result_when_one_item_has_insufficient_content(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $prompt = AiPrompt::factory()->global()->create();
        $validRawNews = RawNewsItem::factory()->for($agency)->create(['original_title' => 'Pendik sahilinde yenileme çalışması başladı', 'original_body' => $this->sourceBody()]);
        $invalidRawNews = RawNewsItem::factory()->for($agency)->create(['original_title' => 'Kısa', 'original_body' => 'çok kısa']);
        $batch = $this->batch($agency, $editor, $prompt, [$validRawNews, $invalidRawNews]);

        app(ContentBatchProcessor::class)->process($batch->id);

        $batch->refresh();
        $this->assertSame(ContentBatchStatus::Partial, $batch->status);
        $this->assertSame(1, $batch->processed_items);
        $this->assertSame(1, $batch->failed_items);
        $this->assertSame(RawNewsStatus::Processed, $validRawNews->fresh()->status);
        $this->assertSame(RawNewsStatus::Failed, $invalidRawNews->fresh()->status);
        $this->assertDatabaseCount('articles', 1);
    }

    public function test_processor_uses_frozen_target_length_from_prompt_snapshot(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $prompt = AiPrompt::factory()->global()->create(['target_length' => 100]);
        $rawNews = RawNewsItem::factory()->for($agency)->create(['original_body' => $this->longSourceBody()]);
        $batch = $this->batch($agency, $editor, $prompt, [$rawNews]);
        $prompt->update(['target_length' => 500]);

        app(ContentBatchProcessor::class)->process($batch->id);

        $article = $batch->items()->firstOrFail()->article;
        $this->assertCount(100, preg_split('/\s+/u', trim($article->body)) ?: []);
    }

    public function test_processor_skips_item_already_processed_by_another_batch(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $prompt = AiPrompt::factory()->global()->create();
        $rawNews = RawNewsItem::factory()->for($agency)->processed()->create();
        $batch = $this->batch($agency, $editor, $prompt, [$rawNews]);

        app(ContentBatchProcessor::class)->process($batch->id);

        $item = $batch->items()->firstOrFail();
        $this->assertSame(ContentBatchItemStatus::Skipped, $item->status);
        $this->assertSame(ContentBatchStatus::Completed, $batch->fresh()->status);
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_automatic_pipeline_uses_ai_and_dispatches_article_finalizer(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'title' => 'AI tarafından üretilen güncel haber başlığı',
                    'summary' => 'Yapay zekâ tarafından hazırlanan haberin doğrulanmış ayrıntıları, kapsamı ve okuyucular için önemli noktaları bu özette yer alıyor.',
                    'body' => $this->generatedBody(),
                ], JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);
        Queue::fake([FinalizeAutomaticArticle::class]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $prompt = AiPrompt::factory()->global()->create();
        ApiIntegration::factory()->for($agency)->create([
            'provider' => IntegrationProvider::OpenAi,
            'model' => 'gpt-5',
            'base_url' => 'https://93.184.216.34/v1/models',
            'auth_type' => IntegrationAuthType::Bearer,
            'credential' => 'automatic-news-key',
            'is_active' => true,
        ]);
        $rawNews = RawNewsItem::factory()->for($agency)->create([
            'original_title' => 'Pendik sahilinde yenileme çalışması başladı',
            'original_body' => $this->sourceBody(),
        ]);
        $batch = $this->batch($agency, $editor, $prompt, [$rawNews]);
        $batch->update(['settings' => [...$batch->settings, 'automatic_pipeline' => true]]);

        app(ContentBatchProcessor::class)->process($batch->id);

        $article = $batch->items()->firstOrFail()->article;
        $this->assertSame('AI tarafından üretilen güncel haber başlığı', $article->title);
        Queue::assertPushedOn('content', FinalizeAutomaticArticle::class, fn (FinalizeAutomaticArticle $job): bool => $job->articleId === $article->id);
    }

    public function test_terminal_job_failure_updates_batch_status(): void
    {
        $batch = ContentBatch::factory()->create(['status' => ContentBatchStatus::Processing]);
        $job = new ProcessContentBatch($batch->id);

        $job->failed(new \RuntimeException('Sağlayıcı yanıt vermedi.'));

        $batch->refresh();
        $this->assertSame(ContentBatchStatus::Failed, $batch->status);
        $this->assertSame('Sağlayıcı yanıt vermedi.', $batch->failure_message);
        $this->assertNotNull($batch->completed_at);
    }

    private function sourceBody(): string
    {
        return implode(' ', [
            'Pendik Belediyesi Doğu Mahallesi sahilinde yenileme çalışması başlattı.',
            'Yürüyüş yolunun zemini ve aydınlatma direkleri proje kapsamında değiştirilecek.',
            'Çocuk parkındaki eski oyun grupları güvenli ekipmanlarla yenilenecek.',
            'Çalışmaların ekim ayının ikinci haftasında tamamlanması planlanıyor.',
            'Sahil yolunun belirli bölümleri uygulama sırasında yaya kullanımına kapatılacak.',
            'Vatandaşların yönlendirme levhalarını takip etmesi istendi.',
            'Proje tamamlandığında yeni dinlenme alanları hizmete açılacak.',
        ]);
    }

    private function longSourceBody(): string
    {
        return implode(' ', array_fill(0, 12, $this->sourceBody()));
    }

    private function generatedBody(): string
    {
        return implode("\n\n", [
            'Pendik Belediyesi, Doğu Mahallesi sahilindeki yürüyüş yolu ile çocuk parkını kapsayan yenileme çalışmalarını başlattı. Ekipler proje alanında zemin sökümü yaptı.',
            'Yürüyüş yolunun yıpranan zemini tamamen değiştirilecek. Sahil hattındaki aydınlatma direkleri de yeni sistemlerle yenilenecek.',
            'Çocuk parkındaki eski oyun grupları kaldırılarak güvenlik standartlarına uygun ekipmanlar yerleştirilecek. Park zemini darbe emici malzemeyle kaplanacak.',
            'Çalışmaların ekim ayının ikinci haftasında tamamlanması hedefleniyor. Uygulama takvimi sahadaki ilerlemeye göre sürdürülecek.',
            'Sahil yolunun belirli bölümleri çalışma boyunca yaya kullanımına geçici olarak kapatılacak. Vatandaşlar alternatif geçişlere yönlendirilecek.',
            'Yenileme tamamlandığında kıyı hattına yeni dinlenme alanları ve banklar eklenecek. Projenin aileler için güvenli kullanım sağlaması amaçlanıyor.',
            'Ekipler açık bölümlerde günlük güvenlik kontrolü yapacak. Tamamlanan kısımlar kontrollerin ardından aşamalı olarak hizmete alınacak.',
        ]);
    }

    /**
     * @param  array<int, RawNewsItem>  $rawNewsItems
     */
    private function batch(Agency $agency, User $creator, AiPrompt $prompt, array $rawNewsItems): ContentBatch
    {
        $batch = ContentBatch::factory()->for($agency)->for($prompt, 'aiPrompt')->create([
            'created_by' => $creator->id,
            'total_items' => count($rawNewsItems),
            'settings' => [
                'prompt_snapshot' => [
                    'name' => $prompt->name,
                    'version' => $prompt->version,
                    'tone' => $prompt->tone->value,
                    'language' => $prompt->language,
                    'target_length' => $prompt->target_length,
                    'temperature' => $prompt->temperature,
                    'system_prompt' => $prompt->system_prompt,
                    'user_prompt_template' => $prompt->user_prompt_template,
                ],
            ],
        ]);

        foreach ($rawNewsItems as $rawNewsItem) {
            ContentBatchItem::factory()
                ->for($batch)
                ->for($rawNewsItem, 'rawNewsItem')
                ->create();
        }

        return $batch;
    }
}
