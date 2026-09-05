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
            'original_body' => implode(' ', array_fill(0, 8, 'Maltepe Belediyesi farklı mahallelerde çalışma ve etkinlik programları başlattı.')),
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('kurumsal liste veya ana sayfa');

        app(NewsContentQualityGate::class)->assertRawNews($item);
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
