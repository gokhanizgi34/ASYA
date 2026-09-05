<?php

namespace Tests\Unit;

use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\RawNewsItem;
use App\Services\AiNewsWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiNewsWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_openai_integration_generates_structured_seo_news_without_unsupported_temperature(): void
    {
        Http::preventStrayRequests();
        $body = $this->istanbulGeneratedBody()
            ."\n\nÇekmeköy Belediyesi haberine göre, çalışmalar kısa sürede tamamlanacak."
            ."\n\nMerak edilenler\n\n– Kimler katıldı? Belediye personeli.\n\nBu haber, resmi duyuru esas alınarak hazırlanmıştır. Kaynak: Belediye (https://example.com)";
        Http::fake([
            'https://93.184.216.34/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'title' => 'İstanbul için önemli ulaşım gelişmesi açıklandı',
                        'summary' => 'İstanbul ulaşımındaki yeni gelişmenin ayrıntıları, uygulama kapsamı ve vatandaşlara etkisi hakkında güncel bilgiler paylaşıldı.',
                        'body' => $body,
                    ], JSON_UNESCAPED_UNICODE)],
                ]],
            ]),
        ]);
        $agency = Agency::factory()->create();
        ApiIntegration::factory()->for($agency)->create([
            'name' => 'OpenAI Haber',
            'provider' => IntegrationProvider::OpenAi,
            'model' => 'gpt-5',
            'base_url' => 'https://93.184.216.34/v1/models',
            'auth_type' => IntegrationAuthType::Bearer,
            'credential' => 'secret-news-key',
            'is_active' => true,
            'is_default' => true,
            'priority' => 1,
        ]);
        $rawNewsItem = RawNewsItem::factory()->for($agency)->create([
            'original_title' => 'İstanbul ulaşımında yeni uygulama başladı',
            'original_body' => $this->istanbulSourceBody(),
        ]);

        $result = app(AiNewsWriter::class)->write($rawNewsItem, ['target_length' => 600]);

        $this->assertSame('İstanbul için önemli ulaşım gelişmesi açıklandı', $result['title']);
        $this->assertStringContainsString('İstanbul ulaşımında', $result['body']);
        $this->assertStringNotContainsString('haberine göre', $result['body']);
        $this->assertStringNotContainsString('Merak edilenler', $result['body']);
        $this->assertStringNotContainsString('Bu haber,', $result['body']);
        $this->assertStringNotContainsString('Kaynak:', $result['body']);
        $this->assertStringNotContainsString('https://', $result['body']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer secret-news-key')
            && ! array_key_exists('temperature', $request->data())
            && data_get($request->data(), 'response_format.type') === 'json_object'
            && str_contains((string) data_get($request->data(), 'messages.0.content'), '"haberine göre"')
            && str_contains((string) data_get($request->data(), 'messages.1.content'), $rawNewsItem->original_title));
    }

    public function test_trend_signal_prompt_requires_a_normal_news_story_without_system_phrases(): void
    {
        Http::preventStrayRequests();
        $body = $this->atiyeGeneratedBody();
        Http::fake([
            'https://93.184.216.34/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'title' => 'Atiye dördüncü kez anne oldu',
                    'summary' => 'Sanatçı Atiye’nin ailesine ilişkin güncel gelişmenin doğrulanabilen ayrıntıları ve yapılan açıklamalar kamuoyuyla paylaşıldı.',
                    'body' => $body,
                ], JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);
        $agency = Agency::factory()->create();
        ApiIntegration::factory()->for($agency)->create([
            'provider' => IntegrationProvider::OpenAi,
            'base_url' => 'https://93.184.216.34/v1/models',
            'credential' => 'trend-key',
            'is_active' => true,
            'is_default' => true,
        ]);
        $rawNewsItem = RawNewsItem::factory()->for($agency)->create([
            'external_id' => 'google-trends-atiye',
            'original_title' => 'Atiye dördüncü kez anne oldu',
            'original_body' => $this->atiyeSourceBody(),
        ]);

        app(AiNewsWriter::class)->write($rawNewsItem, ['target_length' => 600]);

        Http::assertSent(fn (Request $request): bool => str_contains(
            (string) data_get($request->data(), 'messages.1.content'),
            'Başlıkta, özette ve haber gövdesinde "Google Trends"',
        ) && str_contains((string) data_get($request->data(), 'messages.1.content'), 'neden gündem olduğunu oluşturan gerçek olayı'));
    }

    public function test_content_intent_trend_prompt_requests_the_content_instead_of_reporting_the_trend(): void
    {
        Http::fake(['https://93.184.216.34/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'title' => 'En Güzel ve Anlamlı Cuma Mesajları',
            'summary' => 'Sevdiklerinizle paylaşabileceğiniz kısa, anlamlı ve dualı cuma mesajları farklı seçeneklerle bir araya getirildi.',
            'body' => $this->istanbulGeneratedBody(),
        ], JSON_UNESCAPED_UNICODE)]]]])]);
        $agency = Agency::factory()->create();
        ApiIntegration::factory()->for($agency)->create(['provider' => IntegrationProvider::OpenAi, 'base_url' => 'https://93.184.216.34/v1/models', 'credential' => 'trend-key', 'is_active' => true]);
        $rawNewsItem = RawNewsItem::factory()->for($agency)->create([
            'external_id' => 'x-trend-cuma-mesajlari',
            'original_title' => 'Cuma Mesajları',
            'original_body' => $this->istanbulSourceBody(),
        ]);

        app(AiNewsWriter::class)->write($rawNewsItem, ['target_length' => 600]);

        Http::assertSent(fn (Request $request): bool => str_contains(
            (string) data_get($request->data(), 'messages.1.content'),
            'kullanıcıya doğrudan aradığı mesajları, sözleri',
        ));
    }

    public function test_source_or_trend_language_is_rejected_before_publication(): void
    {
        Http::preventStrayRequests();
        $body = str_repeat('Doğrulanmış gelişme aktarılıyor. ', 20).' Google Trends verilerinde arama hacmi yükseldi.';
        Http::fake([
            'https://93.184.216.34/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'title' => 'Google Trends gündeminde yeni gelişme',
                    'summary' => 'Kaynak: örnek haber sitesi',
                    'body' => $body,
                ], JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);
        $agency = Agency::factory()->create();
        ApiIntegration::factory()->for($agency)->create([
            'provider' => IntegrationProvider::OpenAi,
            'base_url' => 'https://93.184.216.34/v1/models',
            'credential' => 'agency-key',
            'is_active' => true,
            'is_default' => true,
        ]);
        $rawNewsItem = RawNewsItem::factory()->for($agency)->create([
            'original_title' => 'İstanbul ulaşımında yeni uygulama başladı',
            'original_body' => $this->istanbulSourceBody(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('kurumsal haber dili');

        app(AiNewsWriter::class)->write($rawNewsItem, ['target_length' => 600]);
    }

    public function test_external_links_and_domain_names_are_removed_without_failing_news_generation(): void
    {
        Http::fake(['https://93.184.216.34/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'title' => 'İstanbul ulaşımında gelişme example.com',
            'summary' => 'Yeni düzenlemenin ayrıntıları https://example.com/haber bağlantısında yer aldı.',
            'body' => $this->istanbulGeneratedBody().' Ayrıntılar www.example.org adresinde paylaşılmıştı.',
            'keywords' => ['İstanbul ulaşım', 'example.com'],
        ], JSON_UNESCAPED_UNICODE)]]]])]);
        $agency = Agency::factory()->create();
        ApiIntegration::factory()->for($agency)->create(['provider' => IntegrationProvider::OpenAi, 'base_url' => 'https://93.184.216.34/v1/models', 'credential' => 'agency-key', 'is_active' => true]);
        $rawNewsItem = RawNewsItem::factory()->for($agency)->create(['original_title' => 'İstanbul ulaşımında yeni uygulama başladı', 'original_body' => $this->istanbulSourceBody()]);

        $result = app(AiNewsWriter::class)->write($rawNewsItem, ['target_length' => 600]);

        $this->assertStringNotContainsString('example.', $result['title'].' '.$result['summary'].' '.$result['body'].' '.implode(' ', $result['keywords']));
    }

    public function test_gemini_is_tried_first_and_quota_error_falls_back_to_next_ai(): void
    {
        Http::preventStrayRequests();
        $body = $this->istanbulGeneratedBody();
        Http::fake([
            'https://93.184.216.34/v1beta/models/gemini-2.5-flash:generateContent*' => Http::response(['error' => ['message' => 'Quota exceeded']], 429),
            'https://93.184.216.34/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'title' => 'İstanbul ulaşımında yeni uygulama başladı',
                    'summary' => 'İstanbul ulaşımındaki yeni uygulamanın kapsamı ve vatandaşların bilmesi gereken güncel ayrıntılar kamuoyuyla paylaşıldı.',
                    'body' => $body,
                    'focus_keyword' => 'İstanbul ulaşım uygulaması',
                    'keywords' => ['İstanbul ulaşım', 'toplu taşıma düzenlemesi'],
                    'hashtags' => ['#İstanbulUlaşım'],
                    'category' => 'Yerel Haberler',
                ], JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);
        $agency = Agency::factory()->create();
        ApiIntegration::factory()->for($agency)->create([
            'name' => 'OpenAI Yedek',
            'provider' => IntegrationProvider::OpenAi,
            'model' => 'gpt-5',
            'base_url' => 'https://93.184.216.34/v1/models',
            'auth_type' => IntegrationAuthType::Bearer,
            'credential' => 'openai-key',
            'is_active' => true,
            'is_default' => true,
            'priority' => 1,
        ]);
        ApiIntegration::factory()->for($agency)->create([
            'name' => 'Gemini Birincil',
            'provider' => IntegrationProvider::GoogleGemini,
            'model' => 'gemini-2.5-flash',
            'base_url' => 'https://93.184.216.34/v1beta/models',
            'auth_type' => IntegrationAuthType::None,
            'credential' => 'gemini-key',
            'is_active' => true,
            'is_default' => false,
            'priority' => 99,
        ]);
        $rawNewsItem = RawNewsItem::factory()->for($agency)->create([
            'original_title' => 'İstanbul ulaşımında yeni uygulama başladı',
            'original_body' => $this->istanbulSourceBody(),
        ]);

        $result = app(AiNewsWriter::class)->write($rawNewsItem, ['target_length' => 600]);

        $this->assertSame(IntegrationProvider::OpenAi->value, $result['ai_provider']);
        $this->assertSame('İstanbul ulaşım uygulaması', $result['focus_keyword']);
        $this->assertSame(['#İstanbulUlaşım'], $result['hashtags']);
        Http::assertSentInOrder([
            fn (Request $request): bool => str_contains($request->url(), 'gemini-2.5-flash:generateContent'),
            fn (Request $request): bool => str_ends_with($request->url(), '/chat/completions'),
        ]);
    }

    private function istanbulSourceBody(): string
    {
        return implode(' ', [
            'İstanbul ulaşımında yeni uygulama pazartesi günü başlayacak.',
            'Belediye otobüs hatlarında sabah saatleri için ek sefer planladı.',
            'Yeni düzenleme Kadıköy ve Üsküdar güzergâhlarında yoğunluğu azaltmayı hedefliyor.',
            'Duraklardaki bilgilendirme ekranları güncel hareket saatlerini gösterecek.',
            'Yolcular değişen sefer bilgilerini belediyenin ulaşım uygulamasından izleyebilecek.',
            'Ekipler ilk hafta boyunca ana aktarma merkezlerinde yolculara yönlendirme yapacak.',
            'Uygulamanın sonuçları yolcu yoğunluğu ve sefer süreleri üzerinden aylık olarak değerlendirilecek.',
        ]);
    }

    private function istanbulGeneratedBody(): string
    {
        return implode("\n\n", [
            'İstanbul ulaşımında yoğunluğu azaltmayı amaçlayan yeni sefer düzenlemesi pazartesi günü yürürlüğe girecek. Belediye, özellikle sabah saatlerinde kullanılan otobüs hatlarına ek seferler koyacak.',
            'Düzenlemenin ilk aşaması Kadıköy ve Üsküdar güzergâhlarını kapsayacak. Yolcu hareketinin yüksek olduğu aktarma noktalarında araç aralıkları yeniden planlanacak.',
            'Duraklarda bulunan elektronik bilgilendirme ekranları yeni hareket saatlerine göre güncellenecek. Yolcular yaklaşan otobüslerin tahmini varış süresini bu ekranlardan takip edebilecek.',
            'Sefer değişiklikleri belediyenin ulaşım uygulamasında da yayımlanacak. Kullanıcılar hat numarasını seçerek güncel kalkış saatlerine ve güzergâh bilgilerine ulaşabilecek.',
            'Ekipler uygulamanın ilk haftasında ana aktarma merkezlerinde görev yapacak. Görevliler yolcuları yeni sefer saatleri ve alternatif ulaşım seçenekleri konusunda bilgilendirecek.',
            'Belediye, uygulamanın etkisini yolcu yoğunluğu ile sefer süreleri üzerinden aylık olarak inceleyecek. İhtiyaç görülmesi halinde araç sayısı ve hareket aralıkları yeniden düzenlenecek.',
            'Yeni planlamayla sabah yoğunluğunda bekleme süresinin azaltılması ve toplu taşıma kullanımının daha düzenli hale getirilmesi hedefleniyor.',
        ]);
    }

    private function atiyeSourceBody(): string
    {
        return implode(' ', [
            'Şarkıcı Atiye dördüncü kez anne oldu ve kızının adını kamuoyuyla paylaştı.',
            'Sanatçı doğumun ardından sağlık durumunun iyi olduğunu bildirdi.',
            'Atiye ailesine katılan bebeğin adının Rumi olduğunu açıkladı.',
            'Aile yakınları anne ile bebeğin dinlendiğini belirtti.',
            'Sanatçı bir süre sahne çalışmalarına ara verecek.',
            'Yeni konser programının ilerleyen haftalarda duyurulması bekleniyor.',
            'Atiye kendisine iletilen kutlama mesajları için takipçilerine teşekkür etti.',
        ]);
    }

    private function atiyeGeneratedBody(): string
    {
        return implode("\n\n", [
            'Şarkıcı Atiye dördüncü kez anne oldu. Sanatçı, ailesine katılan kızının adını Rumi koyduklarını kamuoyuyla paylaştı.',
            'Doğumun ardından açıklama yapan Atiye, kendisinin ve bebeğinin sağlık durumunun iyi olduğunu bildirdi. Anne ile bebeğin bir süre dinleneceği belirtildi.',
            'Atiye, ailesinin yeni üyesi için gönderilen kutlama mesajlarına teşekkür etti. Sanatçı bu süreçte yakınlarıyla birlikte olacağını ifade etti.',
            'Ünlü şarkıcının doğum nedeniyle sahne çalışmalarına kısa bir ara vereceği açıklandı. Planlanan programın dinlenme sürecine göre yeniden şekilleneceği kaydedildi.',
            'Yeni konser takviminin ilerleyen haftalarda duyurulması bekleniyor. Mevcut etkinliklerin durumu resmi program üzerinden paylaşılacak.',
            'Sanatçının takipçileri sosyal medya üzerinden çok sayıda kutlama mesajı gönderdi. Atiye de paylaşılan iyi dilekler için teşekkür mesajı yayımladı.',
            'Atiye ve ailesinin yeni doğan bebek Rumi ile birlikte sağlık durumlarının iyi olduğu ve dinlenme sürecinin devam ettiği bildirildi.',
        ]);
    }
}
