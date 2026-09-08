<?php

namespace Tests\Feature;

use App\ArticleStatus;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\SeoAnalysis;
use App\PublicationStatus;
use App\Services\PublicationSeoRefresher;
use App\SourceTrustStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicationSeoRefresherTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_wordpress_payload_is_refreshed_with_real_seo_fields_and_requeued(): void
    {
        Queue::fake([PublishArticleToWordPress::class]);
        $agency = Agency::factory()->create();
        $target = PublishingTarget::factory()->for($agency)->create(['base_url' => 'https://www.ilcehaber.com']);
        $paragraph = 'Pendik Belediyesi sahil düzenlemesi kapsamında yürüyüş yolunu yeniledi ve çalışma alanında güvenlik önlemleri aldı. Ekipler uygulamanın aşamalarını vatandaşlarla paylaştı.';
        $article = Article::factory()->for($agency)->create([
            'title' => 'Pendik Sahilinde Yenileme Çalışması Tamamlandı',
            'summary' => 'Pendik sahilindeki çalışma tamamlandı.',
            'body' => implode("\n\n", array_fill(0, 4, $paragraph)),
            'status' => ArticleStatus::Published,
            'source_trust_status' => SourceTrustStatus::Verified,
        ]);
        SeoAnalysis::factory()->for($article)->create(['agency_id' => $agency->id, 'focus_keyword' => 'pendik']);
        $publication = Publication::factory()->for($agency)->for($article)->for($target, 'publishingTarget')->create([
            'status' => PublicationStatus::Published,
            'payload' => ['title' => $article->title, 'content' => $article->body, 'excerpt' => $article->summary, 'meta' => []],
        ]);

        app(PublicationSeoRefresher::class)->refresh($publication);

        $publication->refresh();
        $article->refresh();
        $this->assertSame(PublicationStatus::Queued, $publication->status);
        $this->assertStringContainsString('## Pendik Sahilinde Yenileme', $article->body);
        $this->assertGreaterThanOrEqual(120, mb_strlen((string) data_get($publication->payload, 'excerpt')));
        $this->assertSame($article->seoAnalysis->meta_title, data_get($publication->payload, 'meta.rank_math_title'));
        $this->assertSame($article->seoAnalysis->focus_keyword, data_get($publication->payload, 'meta.rank_math_focus_keyword'));
        Queue::assertPushedOn('publishing', PublishArticleToWordPress::class);
    }
}
