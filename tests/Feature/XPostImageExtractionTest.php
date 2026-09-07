<?php

namespace Tests\Feature;

use App\Services\ExternalUrlGuard;
use App\Services\NewsContentExtractor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XPostImageExtractionTest extends TestCase
{
    public function test_x_post_image_is_discovered_from_numbered_photo_pages(): void
    {
        Http::preventStrayRequests();
        $postUrl = 'https://x.com/alitombastr/status/2096851618465550436';
        $imageUrl = 'https://pbs.twimg.com/media/example-photo?format=jpg&name=large';
        $articleHtml = <<<'HTML'
<html><body><article><h1>Öğrenciler için ilk ders zili çaldı</h1><p>Bu sabah eğitim hayatına ilk adımı atan anaokulu ve birinci sınıf öğrencileri için ilk ders zili çaldı.</p><p>Öğrenciler büyük bir heyecanla okullarının yolunu tutarken öğretmenler yeni eğitim dönemi için hazırlıklarını tamamladı.</p><p>Ali Tombaş öğrencilere ve öğretmenlere başarılarla dolu bir eğitim dönemi diledi.</p></article><script>"client:VHdlZXQ6OTk5OTk5OTk5OTk5OTk5OTk5OQ==:media_entities2:0":$R[9]={type:"video",video_info:{}}</script></body></html>
HTML;
        Http::fake(function (Request $request) use ($postUrl, $imageUrl, $articleHtml) {
            return match ($request->url()) {
                $postUrl => Http::response($articleHtml, 200, ['Content-Type' => 'text/html']),
                $postUrl.'/photo/1' => Http::response('<html><head><meta property="og:image" content="https://pbs.twimg.com/profile_images/avatar.jpg"></head></html>', 200, ['Content-Type' => 'text/html']),
                $postUrl.'/photo/2' => Http::response('<html><head><meta property="og:image" content="'.$imageUrl.'"></head></html>', 200, ['Content-Type' => 'text/html']),
                default => Http::response('', 404, ['Content-Type' => 'text/plain']),
            };
        });
        $this->mock(ExternalUrlGuard::class, function ($mock): void {
            $mock->shouldReceive('assertSafe')->atLeast()->once();
        });

        $result = app(NewsContentExtractor::class)->extract($postUrl, 1);

        $this->assertCount(1, $result['items']);
        $this->assertSame($postUrl, $result['items'][0]['url']);
        $this->assertSame($imageUrl, $result['items'][0]['image_url']);
        Http::assertSent(fn (Request $request): bool => $request->url() === $postUrl.'/photo/1');
        Http::assertSent(fn (Request $request): bool => $request->url() === $postUrl.'/photo/2');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === $postUrl.'/photo/3');
    }

    public function test_x_profile_timeline_is_split_into_clean_news_items(): void
    {
        Http::preventStrayRequests();
        $profileUrl = 'https://x.com/alitombastr';
        $statusId = '2096951378224480719';
        $tweetKey = base64_encode('Tweet:'.$statusId);
        $imageUrl = 'https://pbs.twimg.com/media/HRneJpHXYAAcB4m.jpg';
        $createdAtMs = now()->subMinute()->timestamp * 1000;
        $html = '<html><head><meta property="og:title" content="Ali Tombaş 🇹🇷 (@alitombastr) on X"><meta property="og:description" content="Sultanbeyli Belediye Başkanı"></head><body><script>'
            .'"client:'.$tweetKey.':details":$R[1]={__id:"details",__typename:"TBirdData",full_text:"AK Parti Grup Toplantımızı, İlçe Başkanımız Sn. Ayhan Üşdi ile birlikte gerçekleştirdik.\\n\\nSultanbeyli için birlik ve beraberlik ruhuyla çalışmalarımıza devam ediyoruz. https://t.co/4V0szzcP5U",created_at_ms:'.$createdAtMs.'},'
            .'"client:'.$tweetKey.':media_entities2:0":$R[2]={__typename:"ApiMediaEntity",media_url_https:"'.$imageUrl.'",type:"photo",video_info:null},'
            .'"client:'.base64_encode('Tweet:9999999999999999999').':media_entities2:0":$R[9]={__typename:"ApiMediaEntity",type:"video",video_info:{}}'
            .'</script></body></html>';
        Http::fake([$profileUrl => Http::response($html, 200, ['Content-Type' => 'text/html'])]);
        $this->mock(ExternalUrlGuard::class, function ($mock): void {
            $mock->shouldReceive('assertSafe')->once();
        });

        $result = app(NewsContentExtractor::class)->extract($profileUrl, 1);

        $this->assertSame('x_profile_timeline', $result['method']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('Sultanbeyli Belediye Başkanı Ali Tombaş AK Parti Grup Toplantımızı, İlçe Başkanımız Sn. Ayhan Üşdi ile birlikte gerçekleştirdik.', $result['items'][0]['title']);
        $this->assertSame('https://x.com/alitombastr/status/'.$statusId, $result['items'][0]['url']);
        $this->assertSame($imageUrl, $result['items'][0]['image_url']);
        $this->assertStringContainsString('Sultanbeyli için birlik', $result['items'][0]['body']);
        $this->assertStringContainsString('Paylaşımı yapan: Sultanbeyli Belediye Başkanı Ali Tombaş.', $result['items'][0]['body']);
        $this->assertStringNotContainsString('full_text', $result['items'][0]['body']);
        $this->assertStringNotContainsString('https://t.co/', $result['items'][0]['body']);
    }

    public function test_x_profile_identity_supports_minister_titles(): void
    {
        Http::preventStrayRequests();
        $profileUrl = 'https://x.com/uraloglu';
        $statusId = '2096951378224480720';
        $tweetKey = base64_encode('Tweet:'.$statusId);
        $createdAtMs = now()->subMinute()->timestamp * 1000;
        $html = '<html><head><meta property="og:title" content="Abdulkadir Uraloğlu (@uraloglu) on X"><meta name="twitter:description" content="T.C. Ulaştırma ve Altyapı Bakanı"></head><body><script>'
            .'"client:'.$tweetKey.':details":$R[1]={__id:"details",__typename:"TBirdData",full_text:"Yeni ulaşım yatırımının ayrıntılarını kamuoyuyla paylaştık.",created_at_ms:'.$createdAtMs.'}'
            .'</script></body></html>';
        Http::fake([$profileUrl => Http::response($html, 200, ['Content-Type' => 'text/html'])]);
        $this->mock(ExternalUrlGuard::class, function ($mock): void {
            $mock->shouldReceive('assertSafe')->once();
        });

        $result = app(NewsContentExtractor::class)->extract($profileUrl, 1);

        $this->assertSame('T.C. Ulaştırma ve Altyapı Bakanı Abdulkadir Uraloğlu Yeni ulaşım yatırımının ayrıntılarını kamuoyuyla paylaştık.', $result['items'][0]['title']);
        $this->assertStringContainsString('Paylaşımı yapan: T.C. Ulaştırma ve Altyapı Bakanı Abdulkadir Uraloğlu.', $result['items'][0]['body']);
    }

    public function test_x_profile_video_is_marked_and_uses_its_thumbnail(): void
    {
        Http::preventStrayRequests();
        $profileUrl = 'https://x.com/umraniyebeltr';
        $statusId = '2097004425772474609';
        $tweetKey = base64_encode('Tweet:'.$statusId);
        $thumbnailUrl = 'https://pbs.twimg.com/ext_tw_video_thumb/2097004000000000000/pu/img/video-cover.jpg';
        $createdAtMs = now()->subMinute()->timestamp * 1000;
        $html = '<html><head><meta property="og:title" content="Ümraniye Belediyesi (@umraniyebeltr) on X"><meta property="og:description" content="Official X Account of Ümraniye Municipality - Belediye Başkanı Ümraniye Belediyesi"></head><body><script>'
            .'"client:'.$tweetKey.':details":$R[1]={__id:"details",__typename:"TBirdData",full_text:"Avrupa Şampiyonu sporcumuz Mustafa Abacıoğlu’nun başarıya uzanan, hepimize ilham veren hikâyesi... 📺 @trthaber",created_at_ms:'.$createdAtMs.'},'
            .'"client:'.$tweetKey.':media_entities2:0":$R[2]={__typename:"ApiMediaEntity",media_url_https:"'.$thumbnailUrl.'",type:"video",video_info:{variants:[{content_type:"video/mp4",url:"https://video.twimg.com/example.mp4"}]}}'
            .'</script></body></html>';
        Http::fake([$profileUrl => Http::response($html, 200, ['Content-Type' => 'text/html'])]);
        $this->mock(ExternalUrlGuard::class, function ($mock): void {
            $mock->shouldReceive('assertSafe')->once();
        });

        $result = app(NewsContentExtractor::class)->extract($profileUrl, 1);

        $this->assertStringStartsWith('Ümraniye Belediyesi Avrupa Şampiyonu sporcumuz', $result['items'][0]['title']);
        $this->assertStringContainsString('Paylaşımı yapan: Ümraniye Belediyesi.', $result['items'][0]['body']);
        $this->assertStringNotContainsString('Official X Account', $result['items'][0]['title'].' '.$result['items'][0]['body']);
        $this->assertStringNotContainsString('@trthaber', $result['items'][0]['title'].' '.$result['items'][0]['body']);
        $this->assertSame('https://x.com/umraniyebeltr/status/'.$statusId.'/video/1', $result['items'][0]['url']);
        $this->assertSame($thumbnailUrl, $result['items'][0]['image_url']);
    }

    public function test_x_profile_without_tweets_does_not_store_application_state_as_news(): void
    {
        Http::preventStrayRequests();
        $profileUrl = 'https://x.com/alitombastr';
        Http::fake([$profileUrl => Http::response('<html><body><main>{"__typename":"Timeline","instructions":[]}</main></body></html>', 200, ['Content-Type' => 'text/html'])]);
        $this->mock(ExternalUrlGuard::class, function ($mock): void {
            $mock->shouldReceive('assertSafe')->once();
        });

        $result = app(NewsContentExtractor::class)->extract($profileUrl, 1);

        $this->assertSame('x_profile_timeline_empty', $result['method']);
        $this->assertSame([], $result['items']);
    }
}
