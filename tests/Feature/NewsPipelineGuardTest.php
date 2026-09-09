<?php

namespace Tests\Feature;

use App\ArticleStatus;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\Article;
use App\Models\NewsSource;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\RawNewsItem;
use App\Models\User;
use App\PublicationStatus;
use App\Services\NewsContentExtractor;
use App\Services\NewsContentQualityGate;
use App\Services\NewsDuplicateDetector;
use App\Services\NewsFeedImporter;
use App\Services\WordPressPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class NewsPipelineGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_similar_event_titles_are_detected_as_duplicate(): void
    {
        $agency = Agency::factory()->create();
        RawNewsItem::factory()->for($agency)->create(['original_title' => 'Pendik’te 6. Kahve Festivali başladı']);

        $this->assertTrue(app(NewsDuplicateDetector::class)->exists($agency->id, 'Pendik 6. Kahve Festivali başladı'));
    }

    public function test_short_editorial_titles_for_the_same_recent_accident_are_detected(): void
    {
        $sameEvent = app(NewsDuplicateDetector::class)->reportsSameEvent(
            'Diyarbakır’da Otomobil Yayalara Çarptı',
            'Diyarbakır’da Otomobil Kaldırımdaki Anne ve Çocuklarına Çarptı',
            '2026-09-09 08:04:55',
            '2026-09-09 08:04:54',
        );

        $this->assertTrue($sameEvent);
    }

    public function test_same_recent_accident_from_different_sources_is_detected_despite_different_wording(): void
    {
        $this->travelTo('2026-09-09 08:05:00');
        $agency = Agency::factory()->create();
        RawNewsItem::factory()->for($agency)->create([
            'original_title' => 'Diyarbakır\'ın Bağlar ilçesinde kaldırımda yürürken otomobilin çarptığı Hamdiye Öztürk ile çocukları yaralandı',
            'discovered_at' => '2026-09-09 04:13:16',
        ]);

        $duplicateExists = app(NewsDuplicateDetector::class)->exists(
            $agency->id,
            'Diyarbakır’da kavşağa kontrolsüz giren otomobil kaldırıma çıkarak kaldırımda yürüyen yayalara çarptı',
            occurredAt: '2026-09-09 04:54:38',
        );

        $this->assertTrue($duplicateExists);
    }

    public function test_similar_accidents_more_than_six_hours_apart_remain_separate_news(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $agency = Agency::factory()->create();
        RawNewsItem::factory()->for($agency)->create([
            'original_title' => 'Diyarbakır\'ın Bağlar ilçesinde kaldırımda yürürken otomobilin çarptığı Hamdiye Öztürk ile çocukları yaralandı',
            'discovered_at' => '2026-09-09 04:13:16',
        ]);

        $duplicateExists = app(NewsDuplicateDetector::class)->exists(
            $agency->id,
            'Diyarbakır’da kavşağa kontrolsüz giren otomobil kaldırıma çıkarak kaldırımda yürüyen yayalara çarptı',
            occurredAt: '2026-09-09 11:54:38',
        );

        $this->assertFalse($duplicateExists);
    }

    public function test_second_feed_skips_the_same_recent_event_from_another_source(): void
    {
        $this->travelTo('2026-09-09 08:05:00');
        $firstTitle = 'Diyarbakır\'ın Bağlar ilçesinde kaldırımda yürürken otomobilin çarptığı anne ile çocukları yaralandı';
        $secondTitle = 'Diyarbakır’da kavşağa kontrolsüz giren otomobil kaldırıma çıkarak yürüyen yayalara çarptı';
        $body = implode(' ', array_fill(0, 8, 'Otomobil kaldırıma çıkarak yayalara çarptı ve yaralılar hastaneye kaldırıldı.'));
        Http::fake([
            'https://93.184.216.34/first.xml' => Http::response('<rss><channel><item><title>'.htmlspecialchars($firstTitle).'</title><description>'.htmlspecialchars($body).'</description><link>https://93.184.216.34/haber/512</link><pubDate>'.now()->subHours(4)->toRfc2822String().'</pubDate></item></channel></rss>', 200, ['Content-Type' => 'application/rss+xml']),
            'https://93.184.216.34/second.xml' => Http::response('<rss><channel><item><title>'.htmlspecialchars($secondTitle).'</title><description>'.htmlspecialchars($body).'</description><link>https://93.184.216.34/haber/514</link><pubDate>'.now()->subHours(3)->subMinutes(10)->toRfc2822String().'</pubDate></item></channel></rss>', 200, ['Content-Type' => 'application/rss+xml']),
        ]);
        $agency = Agency::factory()->create();
        $firstSource = NewsSource::factory()->for($agency)->create([
            'name' => 'X Haber',
            'domain' => 'xhaber.example',
            'feed_url' => 'https://93.184.216.34/first.xml',
            'feed_format' => 'rss',
        ]);
        $secondSource = NewsSource::factory()->for($agency)->create([
            'name' => 'X Asayiş',
            'domain' => 'xasayis.example',
            'feed_url' => 'https://93.184.216.34/second.xml',
            'feed_format' => 'rss',
        ]);

        $firstResult = app(NewsFeedImporter::class)->import($firstSource);
        $secondResult = app(NewsFeedImporter::class)->import($secondSource);

        $this->assertSame(1, $firstResult['imported']);
        $this->assertSame(0, $secondResult['imported']);
        $this->assertSame(1, $secondResult['skipped']);
        $this->assertDatabaseCount('raw_news_items', 1);
    }

    public function test_import_removes_source_branding_before_duplicate_check(): void
    {
        Http::fake([
            'https://93.184.216.34/feed.xml' => Http::response('<rss><channel><item><title>Fenerbahçe ayrılığı resmen açıkladı - Fanatik Gazetesi Fenerbahçe Haberleri Spor</title><description>'.htmlspecialchars(implode(' ', array_fill(0, 8, 'Kulüp yönetimi transfer sürecine ilişkin güncel kararı açıkladı. Ayrılık görüşmeleri tamamlandı ve resmi işlemler başladı.'))).'</description><link>https://93.184.216.34/haber/1</link><pubDate>'.now()->toRfc2822String().'</pubDate></item></channel></rss>', 200, ['Content-Type' => 'application/rss+xml']),
        ]);
        $agency = Agency::factory()->create();
        $source = NewsSource::factory()->for($agency)->create(['name' => 'Fanatik', 'domain' => 'fanatik.com', 'feed_url' => 'https://93.184.216.34/feed.xml', 'feed_format' => 'rss']);

        app(NewsFeedImporter::class)->import($source);

        $this->assertDatabaseHas('raw_news_items', ['original_title' => 'Fenerbahçe ayrılığı resmen açıkladı']);
    }

    public function test_same_event_with_different_editorial_wording_is_detected(): void
    {
        $agency = Agency::factory()->create();
        RawNewsItem::factory()->for($agency)->create(['original_title' => 'Maltepe Belediyesi stratejik plan anketi başlattı']);

        $this->assertTrue(app(NewsDuplicateDetector::class)->exists($agency->id, 'Maltepe’de stratejik plan anketi ve yeni dönem adımları'));
    }

    public function test_municipality_listing_page_is_rejected_as_non_news(): void
    {
        $item = RawNewsItem::factory()->make([
            'original_title' => 'T.C. Maltepe Belediyesi',
            'original_body' => 'Kurumsal bağlantılar iletişim bilgileri hizmet rehberi ve ana sayfa menüsü.',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('kurumsal liste veya ana sayfa');

        app(NewsContentQualityGate::class)->assertRawNews($item);
    }

    public function test_short_ferry_accident_is_accepted_as_complete_news(): void
    {
        $item = RawNewsItem::factory()->make([
            'source_name' => 'NTV',
            'original_title' => 'Marmara’da panik: Feribot iskeleye çarptı',
            'original_body' => 'İstanbul-Mudanya seferini yapan feribot yanaşma sırasında iskeleye çarptı. Yolcular panik yaşarken yaralanan olmadı. Olayla ilgili inceleme başlatıldı.',
        ]);

        app(NewsContentQualityGate::class)->assertGenerated($item, [
            'title' => 'Marmara’da panik: Feribot iskeleye çarptı',
            'summary' => 'Mudanya İskelesi’nde meydana gelen kazada feribotta hasar oluşurken yolcuların sağlık durumunun iyi olduğu bildirildi.',
            'body' => 'İstanbul ile Mudanya arasında sefer yapan feribot, akşam saatlerinde yanaşma sırasında iskeleye çarptı. Çarpmanın etkisiyle feribotun ön bölümünde hasar meydana geldi. Yolcular kısa süreli panik yaşarken olayda yaralanan olmadığı bildirildi. Kazanın nedeninin belirlenmesi için inceleme başlatıldı.',
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_municipality_or_news_word_in_title_does_not_block_real_event(): void
    {
        $item = RawNewsItem::factory()->make([
            'source_name' => 'Ali Tombaş',
            'source_url' => 'https://www.instagram.com/p/example',
            'original_title' => 'Sultanbeyli Belediyesi Haber: Koordinasyon toplantısı',
            'original_body' => 'Başkan yardımcıları ve birim müdürleriyle koordinasyon toplantısı gerçekleştirildi.',
        ]);

        app(NewsContentQualityGate::class)->assertRawNews($item);

        $this->addToAssertionCount(1);
    }

    public function test_school_bell_social_post_is_accepted_as_a_news_event(): void
    {
        $item = RawNewsItem::factory()->make([
            'source_name' => 'Ali Tombaş',
            'source_url' => 'https://x.com/alitombastr/status/2096851618465550436',
            'original_title' => 'Anaokulu ve birinci sınıf öğrencileri için ilk ders zili çaldı',
            'original_body' => 'Bu sabah eğitim hayatına ilk adımı atan öğrenciler için ilk ders zili çaldı. Ali Tombaş öğrencilere ve öğretmenlere başarılarla dolu bir eğitim dönemi diledi.',
        ]);

        app(NewsContentQualityGate::class)->assertRawNews($item);

        $this->addToAssertionCount(1);
    }

    public function test_real_news_with_a_cookie_banner_suffix_is_accepted(): void
    {
        $item = RawNewsItem::factory()->make([
            'original_title' => 'Beylikdüzü Belediyesi çocuklara özel gezi programı düzenledi',
            'original_body' => 'Beylikdüzü Belediyesi çocuklar için gezi programı düzenledi. Çocuklar Eyüp Sultan Camii ve Miniatürk\'ü ziyaret ederek tarihi yapıları yakından tanıdı. Sitemizde kullanıcı deneyimini geliştirmek ve internet sitesinin verimli çalışmasını sağlamak amacıyla çerezler kullanılmaktadır. Çerez Bildirimi, Gizlilik Bildiriminin bir parçasıdır.',
        ]);

        app(NewsContentQualityGate::class)->assertRawNews($item);

        $this->addToAssertionCount(1);
    }

    public function test_policy_and_cookie_page_is_rejected_as_non_news(): void
    {
        $item = RawNewsItem::factory()->make([
            'original_title' => 'İçişleri Bakanlığı gizlilik ve çerez ilkeleri açıklandı',
            'original_body' => implode(' ', array_fill(0, 8, 'Kişisel verilerin korunması ve çerez politikası kapsamında kullanım koşulları açıklandı.')),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('politika, çerez');

        app(NewsContentQualityGate::class)->assertRawNews($item);
    }

    public function test_advertising_copy_is_rejected_even_when_it_is_long(): void
    {
        $item = RawNewsItem::factory()->make([
            'original_title' => 'Büyük indirim fırsatı başladı',
            'original_body' => implode(' ', [
                'Kampanya fırsatları bugün mağazada başladı ve ziyaretçilere duyuruldu.',
                'Hemen satın al çağrısıyla ürünlerin sepete eklenmesi istendi.',
                'Kupon kodu kullanan müşterilere ek avantaj sağlanacağı açıklandı.',
                'Reklam metninde üyelik fırsatı ve ücretsiz deneme seçeneği sunuldu.',
                'Fiyat karşılaştır bilgileri satış bağlantılarıyla birlikte paylaşıldı.',
                'Satış kampanyasının mağaza ve çevrim içi kanallarda sürdüğü belirtilerek ziyaretçilere ticari çağrı yapıldı.',
            ]),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('reklam, satış veya spam');

        app(NewsContentQualityGate::class)->assertRawNews($item);
    }

    public function test_source_is_not_requested_after_daily_quota_is_reached(): void
    {
        Http::preventStrayRequests();
        $agency = Agency::factory()->create();
        $source = NewsSource::factory()->for($agency)->create(['daily_item_limit' => 1, 'is_active' => true]);
        RawNewsItem::factory()->for($agency)->create(['news_source_id' => $source->id]);

        $result = app(NewsFeedImporter::class)->import($source);

        $this->assertSame('daily_quota_reached', $result['method']);
        $this->assertSame(0, $result['daily_remaining']);
        Http::assertNothingSent();
    }

    public function test_news_older_than_two_days_is_excluded_but_two_day_news_is_kept(): void
    {
        $this->travelTo('2026-09-05 12:00:00');
        $items = [
            ['external_id' => 'old', 'title' => 'Eski haber', 'body' => 'Eski haber metni', 'url' => null, 'image_url' => null, 'published_at' => now()->subDays(2)->subMinute()],
            ['external_id' => 'recent', 'title' => 'Güncel haber', 'body' => 'Güncel haber metni', 'url' => null, 'image_url' => null, 'published_at' => now()->subDays(2)],
        ];

        $extractor = app(NewsContentExtractor::class);
        $method = new \ReflectionMethod($extractor, 'filterRecentItems');
        $method->setAccessible(true);

        $filtered = $method->invoke($extractor, $items);

        $this->assertCount(1, $filtered);
        $this->assertSame('recent', $filtered[0]['external_id']);
    }

    public function test_duplicate_article_is_blocked_again_at_wordpress_boundary(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->agencyOwner()->for($agency)->create();
        $target = PublishingTarget::factory()->for($agency)->create(['is_active' => true]);
        $publishedArticle = Article::factory()->for($agency)->for($user, 'author')->create(['title' => 'Maltepe Belediyesi stratejik plan anketi başlattı']);
        Publication::factory()->for($agency)->for($publishedArticle)->for($target, 'publishingTarget')->create(['status' => PublicationStatus::Published]);
        $candidateArticle = Article::factory()->for($agency)->for($user, 'author')->create(['title' => 'Maltepe’de stratejik plan anketi ve yeni dönem adımları']);
        $candidate = Publication::factory()->for($agency)->for($candidateArticle)->for($target, 'publishingTarget')->create(['status' => PublicationStatus::Queued]);
        $publisher = $this->mock(WordPressPublisher::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('publish');
        });

        (new PublishArticleToWordPress($candidate->id))->handle($publisher, null, app(NewsDuplicateDetector::class));

        $this->assertSame(PublicationStatus::Failed, $candidate->fresh()->status);
        $this->assertStringStartsWith('[KALICI]', (string) $candidate->fresh()->failure_message);
    }

    public function test_same_recent_accident_is_blocked_at_wordpress_boundary(): void
    {
        $this->travelTo('2026-09-09 08:05:00');
        $agency = Agency::factory()->create();
        $user = User::factory()->agencyOwner()->for($agency)->create();
        $target = PublishingTarget::factory()->for($agency)->create(['is_active' => true]);
        $publishedArticle = Article::factory()->for($agency)->for($user, 'author')->create([
            'title' => 'Diyarbakır’da Otomobil Kaldırımdaki Anne ve Çocuklarına Çarptı',
            'created_at' => '2026-09-09 08:04:54',
        ]);
        Publication::factory()->for($agency)->for($publishedArticle)->for($target, 'publishingTarget')->create([
            'status' => PublicationStatus::Published,
            'published_at' => '2026-09-09 08:04:54',
        ]);
        $candidateArticle = Article::factory()->for($agency)->for($user, 'author')->create([
            'title' => 'Diyarbakır’da Otomobil Yayalara Çarptı',
            'created_at' => '2026-09-09 08:04:55',
        ]);
        $candidate = Publication::factory()->for($agency)->for($candidateArticle)->for($target, 'publishingTarget')->create(['status' => PublicationStatus::Queued]);
        $publisher = $this->mock(WordPressPublisher::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('publish');
        });

        (new PublishArticleToWordPress($candidate->id))->handle($publisher, null, app(NewsDuplicateDetector::class));

        $this->assertSame(PublicationStatus::Failed, $candidate->fresh()->status);
        $this->assertSame('[KALICI] Aynı olay farklı bir kaynak veya anlatımla daha önce yayımlandı.', $candidate->fresh()->failure_message);
    }

    public function test_all_failed_publications_can_be_requeued_together(): void
    {
        Queue::fake([PublishArticleToWordPress::class]);
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $target = PublishingTarget::factory()->for($agency)->create(['is_active' => true]);
        $publications = Publication::factory()->count(3)->for($agency)->for($target, 'publishingTarget')->create(['status' => PublicationStatus::Failed]);

        $this->actingAs($owner)->post(route('publications.dispatch-failed'))->assertRedirect()->assertSessionHas('success');

        $publications->each(fn (Publication $publication) => $this->assertSame(PublicationStatus::Queued, $publication->refresh()->status));
        Queue::assertPushed(PublishArticleToWordPress::class, 3);
    }

    public function test_article_bulk_action_moves_selected_articles_to_draft(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $articles = Article::factory()->count(2)->for($agency)->create(['status' => ArticleStatus::PendingApproval]);

        $this->actingAs($owner)->patch(route('articles.bulk-action'), [
            'items' => $articles->pluck('id')->all(),
            'action' => 'draft',
        ])->assertRedirect()->assertSessionHas('success');

        $articles->each(fn (Article $article) => $this->assertSame(ArticleStatus::Draft, $article->refresh()->status));
    }
}
