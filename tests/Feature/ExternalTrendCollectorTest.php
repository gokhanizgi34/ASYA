<?php

namespace Tests\Feature;

use App\IntegrationProvider;
use App\Jobs\ProcessContentBatch;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\PublishingTarget;
use App\Models\RawNewsItem;
use App\Models\SystemSetting;
use App\Models\TrendTopic;
use App\Models\User;
use App\RawNewsStatus;
use App\Services\ExternalTrendCollector;
use App\SettingValueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExternalTrendCollectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_trends_creates_raw_news_and_starts_automatic_pipeline(): void
    {
        Cache::flush();
        Queue::fake([ProcessContentBatch::class]);
        Http::preventStrayRequests();
        config([
            'services.external_trends.google_rss_url' => 'https://93.184.216.34/trending/rss?geo=TR',
            'services.external_trends.max_items_per_run' => 5,
        ]);
        Http::fake([
            'https://93.184.216.34/trending/rss?geo=TR' => Http::response($this->googleTrendsRss(), 200, ['Content-Type' => 'application/rss+xml']),
            'https://93.184.216.34/haber/ramazan-bayrami*' => Http::response('<html><head><meta property="og:title" content="Türkiye’de Ramazan Bayramı tarihleri açıklandı"><meta property="og:image" content="https://93.184.216.34/images/detay.jpg"></head><body><article><p>Ramazan Bayramı tarihleri resmi takvime göre açıklandı ve vatandaşların tatil hazırlıkları başladı.</p><p>Yetkililer, seyahat planı yapanların yol ve hava koşullarına ilişkin güncel duyuruları takip etmesini istedi.</p><p>Ulaşım noktalarında oluşabilecek yoğunluğa karşı ilave tedbirlerin planlandığı, ekiplerin bayram süresince görev başında olacağı bildirildi.</p><p>Vatandaşlara hareket saatinden önce terminal ve havalimanlarında bulunmaları, bilet ve rezervasyon bilgilerini kontrol etmeleri çağrısı yapıldı.</p><p>Bayram programına ilişkin ayrıntıların ilgili kurumların resmi kanallarından ayrıca duyurulacağı belirtildi.</p></article></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $agency = Agency::factory()->create();
        User::factory()->editor()->for($agency)->create();
        ApiIntegration::factory()->ai(IntegrationProvider::GoogleGemini)->for($agency)->create([
            'is_active' => true,
            'credential' => 'gemini-key',
        ]);
        PublishingTarget::factory()->for($agency)->create(['is_active' => true]);

        $result = app(ExternalTrendCollector::class)->collect($agency->id);

        $this->assertSame(1, $result['received']);
        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['queued']);
        $rawNews = RawNewsItem::query()->firstOrFail();
        $this->assertSame(RawNewsStatus::Queued, $rawNews->status);
        $this->assertSame('Google Trends', $rawNews->source_name);
        $this->assertSame('Türkiye’de ramazan bayramı ne zaman', $rawNews->original_title);
        $this->assertStringNotContainsString('arama hacmi', $rawNews->original_body);
        $this->assertSame('https://trends.google.com/explore?geo=TR', $rawNews->source_url);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/haber/ramazan-bayrami'));
        $this->assertDatabaseCount('trend_topics', 1);
        $this->assertTrue((bool) data_get(TrendTopic::query()->firstOrFail()->context, 'external'));
        Queue::assertPushedOn('content', ProcessContentBatch::class);
    }

    public function test_google_trends_stops_importing_when_daily_quota_is_reached(): void
    {
        Cache::flush();
        Queue::fake([ProcessContentBatch::class]);
        Http::preventStrayRequests();
        config([
            'services.external_trends.google_rss_url' => 'https://93.184.216.34/trending/rss?geo=TR',
            'services.external_trends.max_items_per_run' => 5,
        ]);
        Http::fake([
            'https://93.184.216.34/trending/rss?geo=TR' => Http::response($this->googleTrendsRss(), 200, ['Content-Type' => 'application/rss+xml']),
            'https://93.184.216.34/haber/ramazan-bayrami*' => Http::response('<html><body><article><p>Türkiye gündemindeki gelişmelere ilişkin yeterince uzun ve doğrulanabilir örnek haber metni burada yer almaktadır.</p></article></body></html>'),
        ]);
        $agency = Agency::factory()->create();
        SystemSetting::factory()->for($agency)->create([
            'key' => 'trends.google_daily_item_limit',
            'value' => '0',
            'type' => SettingValueType::Integer,
        ]);

        $result = app(ExternalTrendCollector::class)->collect($agency->id);

        $this->assertSame(0, $result['received']);
        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['queued']);
        $this->assertDatabaseCount('raw_news_items', 0);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_incomplete_trend_signal_is_recorded_but_not_sent_to_news_pipeline(): void
    {
        Cache::flush();
        Queue::fake([ProcessContentBatch::class]);
        Http::preventStrayRequests();
        config([
            'services.external_trends.google_rss_url' => 'https://93.184.216.34/trending/rss?geo=TR',
            'services.external_trends.max_items_per_run' => 5,
        ]);
        Http::fake([
            'https://93.184.216.34/trending/rss?geo=TR' => Http::response($this->googleTrendsRss(), 200, ['Content-Type' => 'application/rss+xml']),
            'https://93.184.216.34/haber/ramazan-bayrami*' => Http::response('<html><body><article><p>Kısa ve doğrulanamaz trend özeti.</p></article></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $agency = Agency::factory()->create();

        $result = app(ExternalTrendCollector::class)->collect($agency->id);

        $this->assertSame(['received' => 1, 'imported' => 1, 'queued' => 0], $result);
        $this->assertDatabaseCount('raw_news_items', 1);
        $this->assertDatabaseCount('trend_topics', 1);
        Queue::assertNothingPushed();
    }

    public function test_x_trends_are_collected_from_the_turkey_web_page_without_an_api_integration(): void
    {
        Cache::flush();
        Queue::fake([ProcessContentBatch::class]);
        Http::preventStrayRequests();
        config([
            'services.external_trends.x_endpoint' => 'https://93.184.216.34/x-trends',
            'services.external_trends.x_web_url' => 'https://93.184.216.34/turkey/',
            'services.external_trends.x_max_trends' => 2,
            'services.external_trends.max_items_per_run' => 5,
        ]);
        Http::fake([
            'https://93.184.216.34/x-trends/*' => Http::response(['data' => []]),
            'https://93.184.216.34/turkey/' => Http::response($this->xTrendsHtml(), 200, ['Content-Type' => 'text/html']),
        ]);
        $agency = Agency::factory()->create();
        SystemSetting::factory()->for($agency)->create([
            'key' => 'trends.google_daily_item_limit',
            'value' => '0',
            'type' => SettingValueType::Integer,
        ]);
        User::factory()->editor()->for($agency)->create();
        ApiIntegration::factory()->ai(IntegrationProvider::GoogleGemini)->for($agency)->create(['is_active' => true, 'credential' => 'gemini-key']);
        ApiIntegration::factory()->for($agency)->create([
            'provider' => IntegrationProvider::XTrends,
            'base_url' => 'https://93.184.216.34/x-trends',
            'credential' => 'x-key',
            'is_active' => true,
        ]);
        PublishingTarget::factory()->for($agency)->create(['is_active' => true]);

        $result = app(ExternalTrendCollector::class)->collect($agency->id);

        $this->assertSame(['received' => 2, 'imported' => 2, 'queued' => 2], $result);
        $this->assertDatabaseHas('raw_news_items', ['source_name' => 'X Gündemi', 'original_title' => 'Sivas Kongresi X gündeminde öne çıktı']);
        $this->assertDatabaseHas('trend_topics', ['name' => 'Başakşehir X gündeminde öne çıktı', 'mention_count' => 0]);
        Queue::assertPushedOn('content', ProcessContentBatch::class);
    }

    private function googleTrendsRss(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:ht="https://trends.google.com/trending/rss" version="2.0">
<channel>
<item>
<title>Türkiye’de ramazan bayramı ne zaman</title>
<ht:approx_traffic>20K+</ht:approx_traffic>
<ht:news_item>
<ht:news_item_title>Türkiye’de Ramazan Bayramı tarihleri açıklandı</ht:news_item_title>
<ht:news_item_url>https://93.184.216.34/haber/ramazan-bayrami</ht:news_item_url>
<ht:news_item_source>Örnek Haber</ht:news_item_source>
<ht:news_item_picture>https://93.184.216.34/images/ramazan.jpg</ht:news_item_picture>
</ht:news_item>
</item>
</channel>
</rss>
XML;
    }

    private function xTrendsHtml(): string
    {
        return <<<'HTML'
<!doctype html><html><body>
<div class="trend-card"><ol class="trend-card__list">
<li><span class="trend-name"><a class="trend-link" href="https://twitter.com/search?q=Sivas">Sivas Kongresi</a></span></li>
<li><span class="trend-name"><a class="trend-link" href="https://twitter.com/search?q=Basaksehir">Başakşehir</a></span></li>
<li><span class="trend-name"><a class="trend-link" href="https://twitter.com/search?q=Osimhen">Osimhen</a></span></li>
</ol></div>
<div class="trend-card"><ol><li><span class="trend-name"><a class="trend-link">Eski konu</a></span></li></ol></div>
</body></html>
HTML;
    }
}
