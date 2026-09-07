<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\SocialPublishingAccount;
use App\Services\XPostComposer;
use Tests\TestCase;

class XPostComposerTest extends TestCase
{
    public function test_configured_municipality_is_mentioned_in_municipality_news(): void
    {
        $article = new Article([
            'title' => 'Pendik Belediyesi yeni kültür merkezini açtı',
            'summary' => 'Pendik ilçesindeki merkez hizmete girdi.',
            'body' => 'Pendik Belediyesi tarafından tamamlanan merkez vatandaşların kullanımına açıldı.',
            'source_name' => 'Pendik Belediyesi',
            'source_url' => 'https://www.pendik.bel.tr/tr/haber/yeni-merkez',
        ]);
        $account = new SocialPublishingAccount([
            'account_handle' => '@asyahaber',
            'mention_rules' => ['Pendik Belediyesi' => '@Pendik_Belediye'],
        ]);

        $content = app(XPostComposer::class)->compose($article, $account);

        $this->assertStringContainsString('@Pendik_Belediye', $content);
        $this->assertLessThanOrEqual(255, mb_strlen($content));
    }

    public function test_source_x_handle_is_used_when_the_municipal_source_identity_appears_in_news(): void
    {
        $article = new Article([
            'title' => 'Sultanbeyli Belediye Başkanı Ali Tombaş esnafı ziyaret etti',
            'summary' => 'Ali Tombaş ilçe esnafıyla bir araya geldi.',
            'body' => 'Sultanbeyli Belediye Başkanı Ali Tombaş ziyaret sırasında vatandaşlarla görüştü.',
            'source_name' => 'Ali Tombaş',
            'source_url' => 'https://x.com/alitombastr/status/2096851618465550436/photo/1',
        ]);
        $account = new SocialPublishingAccount(['account_handle' => '@asyahaber']);

        $content = app(XPostComposer::class)->compose($article, $account);

        $this->assertStringContainsString('@alitombastr', $content);
    }

    public function test_non_municipality_news_has_no_recipient_mention(): void
    {
        $article = new Article([
            'title' => 'Apple yeni telefon modelini tanıttı',
            'summary' => 'Yeni modelin özellikleri açıklandı.',
            'body' => 'Şirket yeni telefon modelini düzenlenen etkinlikte tanıttı.',
            'source_name' => 'Teknoloji Haberleri',
            'source_url' => 'https://example.com/apple-yeni-model',
        ]);
        $account = new SocialPublishingAccount([
            'account_handle' => '@asyahaber',
            'mention_rules' => ['Pendik Belediyesi' => '@Pendik_Belediye'],
        ]);

        $content = app(XPostComposer::class)->compose($article, $account);

        $this->assertStringNotContainsString('@', $content);
    }
}
