<?php

namespace Tests\Unit;

use App\Services\NewsTextSanitizer;
use PHPUnit\Framework\TestCase;

class NewsTextSanitizerTest extends TestCase
{
    public function test_cookie_banner_suffix_is_removed_without_losing_news_body(): void
    {
        $news = "Beylikdüzü Belediyesi çocuklar için gezi programı düzenledi.\n\nÇocuklar Eyüp Sultan Camii ve Miniatürk'ü ziyaret ederek tarihi yapıları yakından tanıdı.";
        $cookie = 'Sitemizde kullanıcı deneyimini geliştirmek ve internet sitesinin verimli çalışmasını sağlamak amacıyla çerezler kullanılmaktadır. Çerez Bildirimi, Gizlilik Bildiriminin bir parçasıdır.';

        $cleaned = (new NewsTextSanitizer)->clean($news."\n\n".$cookie);

        $this->assertSame($news, $cleaned);
    }

    public function test_genuine_privacy_news_is_not_removed(): void
    {
        $news = 'Belediye meclisi yeni gizlilik politikasını görüştü ve kişisel verilerin korunmasına ilişkin karar aldı.';

        $cleaned = (new NewsTextSanitizer)->clean($news);

        $this->assertSame($news, $cleaned);
    }
}
