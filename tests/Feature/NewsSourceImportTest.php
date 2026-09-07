<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\NewsSource;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use App\Services\NativeTlsHttpFetcher;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsSourceImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-29 15:00:00'));
    }

    public function test_rss_source_imports_news_to_raw_news_pool_and_skips_duplicates(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/gundem.rss' => Http::response(PHP_EOL.PHP_EOL.$this->rss(), 200, ['Content-Type' => 'application/rss+xml'])]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/gundem.rss']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertRedirect()->assertSessionHas('success');
        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseCount('raw_news_items', 2);
        $this->assertSame(2, RawNewsItem::query()->where('agency_id', $agency->id)->count());
        $this->assertSame(2, $source->refresh()->last_item_count);
        $this->assertNull($source->last_fetch_error);
        Http::assertSentCount(2);
    }

    public function test_rss_summary_is_replaced_with_full_linked_article_body(): void
    {
        Http::preventStrayRequests();
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><title>Pendik Haberleri</title>
<item><guid>pendik-1</guid><title>Pendik sahilinde yenileme başladı</title><link>https://93.184.216.34/haber/pendik-sahil</link><description>Kısa akış özeti yalnızca duyuru bilgisini içeriyor.</description><pubDate>Fri, 28 Aug 2026 12:00:00 GMT</pubDate></item>
</channel></rss>
XML;
        Http::fake([
            'https://93.184.216.34/gundem.rss' => Http::response($rss, 200, ['Content-Type' => 'application/rss+xml']),
            'https://93.184.216.34/haber/pendik-sahil' => Http::response($this->articleHtml('Pendik sahilinde yenileme başladı', 'pendik.jpg'), 200, ['Content-Type' => 'text/html']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/gundem.rss']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))
            ->assertRedirect()
            ->assertSessionHas('success');

        $rawNews = RawNewsItem::query()->firstOrFail();
        $this->assertStringContainsString('uygulama takviminin izlenmesini sağlar', $rawNews->original_body);
        $this->assertGreaterThan(350, mb_strlen($rawNews->original_body));
        Http::assertSentCount(2);
    }

    public function test_existing_invalid_raw_record_is_refreshed_even_when_clean_article_is_shorter(): void
    {
        Http::preventStrayRequests();
        $title = 'Pendik sahilinde yenileme başladı';
        $articleUrl = 'https://93.184.216.34/haber/pendik-sahil';
        $rss = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Pendik</title><item><guid>pendik-1</guid><title>'.$title.'</title><link>'.$articleUrl.'</link><description>Bu kısa akış özeti haberin yalnızca duyuru bilgisini içeriyor.</description><pubDate>Fri, 28 Aug 2026 12:00:00 GMT</pubDate></item></channel></rss>';
        Http::fake([
            'https://93.184.216.34/gundem.rss' => Http::response($rss, 200, ['Content-Type' => 'application/rss+xml']),
            $articleUrl => Http::response($this->articleHtml($title, 'pendik.jpg'), 200, ['Content-Type' => 'text/html']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/gundem.rss']);
        $existing = RawNewsItem::factory()->processed()->for($agency)->create([
            'source_name' => $source->name,
            'source_url' => $articleUrl,
            'original_title' => $title,
            'original_body' => str_repeat('İmar Durumu işlemi hakkında detaylı bilgi için lütfen tıklayınız. ', 30),
            'checksum' => hash('sha256', strtolower($articleUrl).'|'.mb_strtolower($title)),
        ]);

        $response = $this->actingAs($editor)->post(route('source-trust.sources.import', $source));
        $response->assertRedirect();
        $this->assertTrue($response->getSession()->has('success'), (string) $response->getSession()->get('error'));

        $existing->refresh();
        $this->assertDatabaseCount('raw_news_items', 1);
        $this->assertStringContainsString('uygulama takviminin izlenmesini sağlar', $existing->original_body);
        $this->assertSame(RawNewsStatus::Pending, $existing->status);
        $this->assertNull($existing->processed_at);
    }

    public function test_existing_item_is_refreshed_when_a_new_extraction_adds_its_missing_image(): void
    {
        Http::preventStrayRequests();
        $title = 'Ali Tombaş öğrenciler için ilk ders zilini paylaştı';
        $articleUrl = 'https://93.184.216.34/haber/okul';
        $body = 'Anaokulu ve birinci sınıf öğrencileri için ilk ders zili çaldı. Ali Tombaş öğrencilere ve öğretmenlere başarılarla dolu bir eğitim dönemi diledi.';
        $imageUrl = 'https://pbs.twimg.com/media/example-photo?format=jpg&name=large';
        Http::fake([
            'https://93.184.216.34/news.json' => Http::response([
                'articles' => [[
                    'id' => 'x-school',
                    'title' => $title,
                    'body' => $body,
                    'url' => $articleUrl,
                    'image' => $imageUrl,
                    'published_at' => '2026-08-30T10:00:00+03:00',
                ]],
            ], 200, ['Content-Type' => 'application/json']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/news.json']);
        $existing = RawNewsItem::factory()->for($agency)->create([
            'news_source_id' => $source->id,
            'source_url' => $articleUrl,
            'original_title' => $title,
            'original_body' => $body,
            'original_image_url' => null,
        ]);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertRedirect()->assertSessionHas('success');

        $this->assertSame($imageUrl, $existing->refresh()->original_image_url);
    }

    public function test_html_news_page_automatically_discovers_and_saves_wordpress_feed(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/haber/' => Http::response('<html><head><title>Haberler</title></head></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']),
            'https://93.184.216.34/haber/feed/' => Http::response(PHP_EOL.PHP_EOL.$this->rss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haber/']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('raw_news_items', 2);
        $this->assertSame('https://93.184.216.34/haber/feed/', $source->refresh()->feed_url);
        $this->assertNull($source->last_fetch_error);
        Http::assertSentCount(2);
    }

    public function test_foreign_tenant_cannot_import_source(): void
    {
        Http::preventStrayRequests();
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($otherAgency)->create();

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertForbidden();

        Http::assertNothingSent();
        $this->assertDatabaseCount('raw_news_items', 0);
    }

    public function test_invalid_feed_is_reported_without_creating_news(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/broken.rss' => Http::response('not xml', 200)]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/broken.rss']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseCount('raw_news_items', 0);
        $this->assertNotNull($source->refresh()->last_fetch_error);
        Http::assertSentCount(1);
    }

    public function test_direct_json_api_imports_common_article_fields(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/news.json' => Http::response([
                'articles' => [[
                    'id' => 44,
                    'title' => 'JSON API üzerinden gelen güncel haber',
                    'body' => 'Bu içerik genel JSON API alanlarının otomatik eşleştirilmesini doğrulamak için yeterince uzundur.',
                    'url' => 'https://93.184.216.34/haber/json-api-haberi',
                    'image' => 'https://93.184.216.34/images/news.jpg',
                    'published_at' => '2026-08-30T10:00:00+03:00',
                ]],
            ], 200, ['Content-Type' => 'application/json']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/news.json']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'JSON API'));

        $this->assertDatabaseHas('raw_news_items', [
            'agency_id' => $agency->id,
            'original_title' => 'JSON API üzerinden gelen güncel haber',
        ]);
        $this->assertSame('json_api', $source->refresh()->last_ingestion_method);
        $this->assertNotNull($source->last_content_fingerprint);
    }

    public function test_wordpress_rest_api_is_discovered_when_feed_is_unavailable(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/haberler' => Http::response('<html><head><link rel="https://api.w.org/" href="https://93.184.216.34/wp-json/"></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://93.184.216.34/haberler/feed/' => Http::response('', 404),
            'https://93.184.216.34/wp-json/wp/v2/posts?per_page=20&_embed=1' => Http::response([[
                'id' => 81,
                'date_gmt' => '2026-08-30T08:00:00',
                'link' => 'https://93.184.216.34/haber/wp-haberi',
                'title' => ['rendered' => 'WordPress API güncel haberi'],
                'content' => ['rendered' => '<p>WordPress REST API üzerinden alınan haber içeriği yeterli uzunluktadır ve havuza kaydedilir.</p>'],
                '_embedded' => ['wp:featuredmedia' => [['source_url' => 'https://93.184.216.34/media/cover.jpg']]],
            ]], 200, ['Content-Type' => 'application/json']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haberler']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'WordPress JSON API'));

        $this->assertDatabaseHas('raw_news_items', ['original_title' => 'WordPress API güncel haberi']);
        $this->assertSame('wordpress_json_api', $source->refresh()->last_ingestion_method);
        $this->assertStringContainsString('/wp-json/wp/v2/posts', $source->feed_url);
    }

    public function test_html_listing_is_safely_crawled_on_same_domain(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/haberler' => Http::response(
                '<html><body><section class="haber-listesi"><h2><a href="/haber/birinci">Birinci belediye haberi başlığı</a></h2><h2><a href="/haber/ikinci">İkinci belediye haberi başlığı</a></h2></section></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'https://93.184.216.34/haberler/feed/' => Http::response('', 404),
            'https://93.184.216.34/wp-json/wp/v2/posts?per_page=20&_embed=1' => Http::response('', 404),
            'https://93.184.216.34/haber/birinci' => Http::response($this->articleHtml('Birinci belediye haberi başlığı', 'birinci.jpg'), 200, ['Content-Type' => 'text/html']),
            'https://93.184.216.34/haber/ikinci' => Http::response($this->articleHtml('İkinci belediye haberi başlığı', 'ikinci.jpg'), 200, ['Content-Type' => 'text/html']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haberler']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'HTML / DOM tarama'));

        $this->assertDatabaseCount('raw_news_items', 2);
        $this->assertSame('html_dom_crawl', $source->refresh()->last_ingestion_method);
        $this->assertSame(2, $source->last_crawled_pages);
    }

    public function test_visual_ai_fallback_is_disabled_when_static_methods_fail(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/haberler' => Http::response('<html><body><div>Dinamik haber alanı</div></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://93.184.216.34/haberler/feed/' => Http::response('', 404),
            'https://93.184.216.34/wp-json/wp/v2/posts?per_page=20&_embed=1' => Http::response('', 404),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haberler']);
        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '0 yeni haber'));

        $this->assertDatabaseCount('raw_news_items', 0);
        $this->assertSame('html_dom_crawl_empty', $source->refresh()->last_ingestion_method);
    }

    public function test_htmx_fragments_are_expanded_before_visual_ai_fallback(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/haberler' => Http::response('<html><body><div hx-get="/ic/2044/"></div></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://93.184.216.34/haberler/feed/' => Http::response('', 404),
            'https://93.184.216.34/wp-json/wp/v2/posts?per_page=20&_embed=1' => Http::response('', 404),
            'https://93.184.216.34/ic/2044/' => Http::response('<div><a class="post-title" href="/haberler/dinamik-haber">Dinamik belediye haberinin başlığı</a></div>', 200, ['Content-Type' => 'text/html']),
            'https://93.184.216.34/haberler/dinamik-haber' => Http::response($this->articleHtml('Dinamik belediye haberinin başlığı', 'dinamik.jpg'), 200, ['Content-Type' => 'text/html']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haberler']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'HTML / DOM tarama'));

        $this->assertDatabaseHas('raw_news_items', ['original_title' => 'Dinamik belediye haberinin başlığı']);
        $this->assertSame('html_dom_crawl', $source->refresh()->last_ingestion_method);
    }

    public function test_curl_error_60_uses_native_ca_fallback_without_disabling_verification(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn () => throw new ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate'));
        $this->mock(NativeTlsHttpFetcher::class, function ($mock): void {
            $mock->shouldReceive('caBundlePath')->andReturn(null);
            $mock->shouldReceive('fetch')->andReturnUsing(function (string $url): ClientResponse {
                if (str_ends_with($url, '/feed/') || str_contains($url, '/wp-json/')) {
                    return new ClientResponse(new PsrResponse(404, ['Content-Type' => 'text/plain'], ''));
                }

                if (str_contains($url, '/haber/ssl-haberi')) {
                    return new ClientResponse(new PsrResponse(200, ['Content-Type' => 'text/html'], $this->articleHtml('SSL zinciri güvenli haber başlığı', 'ssl.jpg')));
                }

                return new ClientResponse(new PsrResponse(200, ['Content-Type' => 'text/html'], '<html><body><h2><a href="/haber/ssl-haberi">SSL zinciri güvenli haber başlığı</a></h2></body></html>'));
            });
        });
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haberler']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('raw_news_items', ['original_title' => 'SSL zinciri güvenli haber başlığı']);
        $this->assertNull($source->refresh()->last_fetch_error);
    }

    public function test_certificate_failure_automatically_enables_insecure_tls_after_successful_retry(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn () => throw new ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate'));
        $this->mock(NativeTlsHttpFetcher::class, function ($mock): void {
            $mock->shouldReceive('caBundlePath')->andReturn(null);
            $mock->shouldReceive('fetch')->andReturnUsing(function (string $url, string $accept, string $userAgent, int $maxBodyBytes = 5_000_000, bool $allowInsecureTls = false): ClientResponse {
                if (! $allowInsecureTls) {
                    throw new \RuntimeException('Kaynağın HTTPS sertifika zinciri doğrulanamadı.');
                }

                if (str_ends_with($url, '/feed/') || str_contains($url, '/wp-json/')) {
                    return new ClientResponse(new PsrResponse(404, ['Content-Type' => 'text/plain'], ''));
                }

                if (str_contains($url, '/haber/ssl-otomatik')) {
                    return new ClientResponse(new PsrResponse(200, ['Content-Type' => 'text/html'], $this->articleHtml('Otomatik TLS fallback haber başlığı', 'ssl-auto.jpg')));
                }

                return new ClientResponse(new PsrResponse(200, ['Content-Type' => 'text/html'], '<html><body><h2><a href="/haber/ssl-otomatik">Otomatik TLS fallback haber başlığı</a></h2></body></html>'));
            });
        });
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create([
            'feed_url' => 'https://93.184.216.34/haberler',
            'allow_insecure_tls' => false,
        ]);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertRedirect()->assertSessionHas('success');

        $this->assertTrue($source->refresh()->allow_insecure_tls);
        $this->assertNull($source->last_fetch_error);
        $this->assertDatabaseHas('raw_news_items', ['original_title' => 'Otomatik TLS fallback haber başlığı']);
    }

    public function test_html_crawler_ignores_weather_heading_and_uses_the_url_matching_news_title(): void
    {
        Http::preventStrayRequests();
        $title = 'Beykoz Metrosu için Kritik Ziyaret: Hedef 2027';
        $article = '<html><head><title>'.$title.' - T.C. Beykoz Belediyesi</title></head><body><header><h1>25°C</h1></header><main><h4>'.$title.'</h4><section class="news-content"><p>Ulaştırma ve Altyapı Bakanı Beykoz ulaşım yatırımlarını görüşmek üzere belediye yönetimini ziyaret etti.</p><p>Görüşmede Ümraniye Kavacık Beykoz Raylı Sistem Hattı ve projenin yatırım programına alınması ele alındı.</p><p>Hattın detay projelerinin tamamlanmasının ardından çalışmalara 2027 yılında başlanmasının hedeflendiği açıklandı.</p></section></main></body></html>';
        Http::fake([
            'https://93.184.216.34/haberler' => Http::response('<html><body><a href="/haber/beykoz-metrosu-icin-kritik-ziyaret-hedef-2027">'.$title.'</a></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://93.184.216.34/haberler/feed/' => Http::response('', 404),
            'https://93.184.216.34/wp-json/wp/v2/posts?per_page=20&_embed=1' => Http::response('', 404),
            'https://93.184.216.34/haber/beykoz-metrosu-icin-kritik-ziyaret-hedef-2027' => Http::response($article, 200, ['Content-Type' => 'text/html']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haberler']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertRedirect()->assertSessionHas('success');

        $rawNewsItem = RawNewsItem::query()->sole();
        $this->assertSame($title, $rawNewsItem->original_title);
        $this->assertNotSame('25°C', $rawNewsItem->original_title);
        $this->assertSame(RawNewsStatus::Pending, $rawNewsItem->status);
    }

    public function test_html_crawler_extracts_lazy_loaded_source_image(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/haberler' => Http::response('<html><body><h2><a href="/haber/lazy">Lazy görselli haber başlığı</a></h2></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://93.184.216.34/haberler/feed/' => Http::response('', 404),
            'https://93.184.216.34/wp-json/wp/v2/posts?per_page=20&_embed=1' => Http::response('', 404),
            'https://93.184.216.34/haber/lazy' => Http::response('<html><head><meta property="og:title" content="Lazy görselli haber başlığı"></head><body><article><h1>Lazy görselli haber başlığı</h1><img srcset="/images/lazy-small.webp 640w, /images/lazy-cover.webp 1600w"><p>Bu haber ayrıntısı, lazy-load görselinin kaynak haberden alınmasını doğrulamak için yeterince uzun bir metin içerir.</p><p>İkinci paragraf haber gövdesinin geçerli uzunluğa ulaşmasını sağlar.</p></article></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haberler']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertRedirect()->assertSessionHas('success');

        $this->assertSame('https://93.184.216.34/images/lazy-cover.webp', RawNewsItem::query()->firstOrFail()->original_image_url);
    }

    public function test_html_crawler_does_not_store_the_source_homepage_as_a_news_image(): void
    {
        Http::fake([
            'https://93.184.216.34/haberler' => Http::response('<html><body><h2><a href="/haber/logo">Görseli olmayan haber başlığı</a></h2></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://93.184.216.34/haberler/feed/' => Http::response('', 404),
            'https://93.184.216.34/wp-json/wp/v2/posts?per_page=20&_embed=1' => Http::response('', 404),
            'https://93.184.216.34/haber/logo' => Http::response($this->articleHtmlWithImage('Görseli olmayan haber başlığı', 'https://93.184.216.34'), 200, ['Content-Type' => 'text/html']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haberler']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertRedirect();

        $this->assertNull(RawNewsItem::query()->firstOrFail()->original_image_url);
    }

    public function test_html_crawler_removes_cookie_banner_from_stored_article_body(): void
    {
        Http::preventStrayRequests();
        $article = <<<'HTML'
<html><body><article><h1>Beylikdüzü Belediyesi çocuklara özel gezi programı düzenledi</h1><p>Beylikdüzü Belediyesi sağlık kuruluşlarında ücretsiz sünnet ettirilen çocuklar için gezi programı düzenledi.</p><p>Çocuklar Eyüp Sultan Camii ve Miniatürk'ü ziyaret ederek tarihi ve kültürel yapıları yakından tanıdı.</p><p>Ailelerin de katıldığı programda çocuklara çeşitli ikramlar ve hediyeler sunuldu.</p><p>Sitemizde kullanıcı deneyimini geliştirmek ve internet sitesinin verimli çalışmasını sağlamak amacıyla çerezler kullanılmaktadır. Çerez Bildirimi, Gizlilik Bildiriminin bir parçasıdır.</p></article></body></html>
HTML;
        Http::fake([
            'https://93.184.216.34/haberler' => Http::response('<html><body><h2><a href="/haber/gezi">Beylikdüzü Belediyesi çocuklara özel gezi programı düzenledi</a></h2></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://93.184.216.34/haberler/feed/' => Http::response('', 404),
            'https://93.184.216.34/wp-json/wp/v2/posts?per_page=20&_embed=1' => Http::response('', 404),
            'https://93.184.216.34/haber/gezi' => Http::response($article, 200, ['Content-Type' => 'text/html']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haberler']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertRedirect()->assertSessionHas('success');

        $body = RawNewsItem::query()->firstOrFail()->original_body;
        $this->assertStringContainsString('Eyüp Sultan Camii', $body);
        $this->assertStringNotContainsString('çerezler kullanılmaktadır', $body);
        $this->assertStringNotContainsString('Gizlilik Bildirimi', $body);
    }

    public function test_html_crawler_uses_listing_card_image_when_article_has_no_image(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/haberler' => Http::response('<html><body><article class="haber-karti"><a href="/haber/kart-gorseli"><img data-src="/images/kart-cover.webp"><h2>Kart görselli belediye haber başlığı</h2></a></article></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://93.184.216.34/haberler/feed/' => Http::response('', 404),
            'https://93.184.216.34/wp-json/wp/v2/posts?per_page=20&_embed=1' => Http::response('', 404),
            'https://93.184.216.34/haber/kart-gorseli' => Http::response('<html><body><article><h1>Kart görselli belediye haber başlığı</h1><p>Belediye tarafından yürütülen çalışmanın ilk aşaması tamamlandı ve uygulama sahasında yeni dönem başladı.</p><p>Ekipler program kapsamında belirlenen noktalarda çalışmalarını planlı biçimde sürdürüyor.</p><p>Çalışmanın ayrıntıları ve uygulama takvimi vatandaşlarla paylaşıldı.</p></article></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haberler']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertRedirect()->assertSessionHas('success');

        $this->assertSame('https://93.184.216.34/images/kart-cover.webp', RawNewsItem::query()->firstOrFail()->original_image_url);
    }

    public function test_html_crawler_reads_article_image_from_json_ld(): void
    {
        Http::preventStrayRequests();
        $jsonLd = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => 'JSON LD görselli belediye haber başlığı',
            'image' => [['url' => '/images/json-ld-cover.jpg']],
            'articleBody' => str_repeat('Belediye ekipleri proje alanındaki çalışmaları planlanan program kapsamında sürdürüyor. ', 5),
        ], JSON_THROW_ON_ERROR);
        Http::fake([
            'https://93.184.216.34/haberler' => Http::response('<html><body><h2><a href="/haber/json-ld">JSON LD görselli belediye haber başlığı</a></h2></body></html>', 200, ['Content-Type' => 'text/html']),
            'https://93.184.216.34/haberler/feed/' => Http::response('', 404),
            'https://93.184.216.34/wp-json/wp/v2/posts?per_page=20&_embed=1' => Http::response('', 404),
            'https://93.184.216.34/haber/json-ld' => Http::response('<html><head><script type="application/ld+json">'.$jsonLd.'</script></head><body><h1>JSON LD görselli belediye haber başlığı</h1></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create(['feed_url' => 'https://93.184.216.34/haberler']);

        $this->actingAs($editor)->post(route('source-trust.sources.import', $source))->assertRedirect()->assertSessionHas('success');

        $this->assertSame('https://93.184.216.34/images/json-ld-cover.jpg', RawNewsItem::query()->firstOrFail()->original_image_url);
    }

    private function articleHtml(string $title, string $image): string
    {
        return '<html><head><meta property="og:title" content="'.$title.'"><meta property="og:image" content="/images/'.$image.'"><meta property="article:published_time" content="2026-08-30T12:00:00+03:00"></head><body><article><h1>'.$title.'</h1><p>Bu haber ayrıntısı, HTML DOM ayrıştırıcısının güvenli biçimde içerik çıkarmasını doğrulamak için yeterince uzun bir metin içerir.</p><p>İkinci paragraf haber gövdesinin eksiksiz olarak ham haber havuzuna alınmasını sağlar.</p><p>Belediye ekiplerinin sahadaki çalışmaları belirlenen proje alanında planlı biçimde devam etmektedir.</p><p>Vatandaşlara geçici güzergâh değişikliklerini gösteren yönlendirme levhaları yerleştirilmiştir.</p><p>Son paragraf, tam haber gövdesinin kısa akış özetinin yerine geçtiğini ve uygulama takviminin izlenmesini sağlar.</p></article></body></html>';
    }

    private function articleHtmlWithImage(string $title, string $image): string
    {
        return '<html><head><meta property="og:title" content="'.$title.'"><meta property="og:image" content="'.$image.'"></head><body><article><h1>'.$title.'</h1><p>Bu haber ayrıntısı, HTML DOM ayrıştırıcısının güvenli biçimde içerik çıkarmasını doğrulamak için yeterince uzun bir metin içerir.</p><p>İkinci paragraf haber gövdesinin eksiksiz olarak ham haber havuzuna alınmasını sağlar.</p><p>Belediye ekiplerinin sahadaki çalışmaları belirlenen proje alanında planlı biçimde devam etmektedir.</p><p>Vatandaşlara geçici güzergâh değişikliklerini gösteren yönlendirme levhaları yerleştirilmiştir.</p><p>Son paragraf, tam haber gövdesinin kısa akış özetinin yerine geçtiğini ve uygulama takviminin izlenmesini sağlar.</p></article></body></html>';
    }

    private function rss(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel><title>Test</title>
<item><guid>haber-1</guid><title>Birinci örnek haber başlığı</title><link>https://example.com/haber-1</link><description>Birinci haberin sisteme alınması için yeterince uzun açıklama metni.</description><pubDate>Fri, 28 Aug 2026 12:00:00 GMT</pubDate></item>
<item><guid>haber-2</guid><title>İkinci örnek haber başlığı</title><link>https://example.com/haber-2</link><description>İkinci haberin sisteme alınması için yeterince uzun açıklama metni.</description><pubDate>Fri, 28 Aug 2026 13:00:00 GMT</pubDate></item>
</channel></rss>
XML;
    }
}
