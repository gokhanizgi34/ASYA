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
<html><body><article><h1>Öğrenciler için ilk ders zili çaldı</h1><p>Bu sabah eğitim hayatına ilk adımı atan anaokulu ve birinci sınıf öğrencileri için ilk ders zili çaldı.</p><p>Öğrenciler büyük bir heyecanla okullarının yolunu tutarken öğretmenler yeni eğitim dönemi için hazırlıklarını tamamladı.</p><p>Ali Tombaş öğrencilere ve öğretmenlere başarılarla dolu bir eğitim dönemi diledi.</p></article></body></html>
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
        $html = '<html><head><meta property="og:title" content="Ali Tombaş (@alitombastr) on X"></head><body><script>'
            .'"client:'.$tweetKey.':details":$R[1]={__id:"details",__typename:"TBirdData",full_text:"AK Parti Grup Toplantımızı gerçekleştirdik.\\n\\nSultanbeyli için birlik ve beraberlik ruhuyla çalışmalarımıza devam ediyoruz. https://t.co/4V0szzcP5U",created_at_ms:'.$createdAtMs.'},'
            .'"client:'.$tweetKey.':media_entities2:0":$R[2]={__typename:"ApiMediaEntity",media_url_https:"'.$imageUrl.'",type:"photo"}'
            .'</script></body></html>';
        Http::fake([$profileUrl => Http::response($html, 200, ['Content-Type' => 'text/html'])]);
        $this->mock(ExternalUrlGuard::class, function ($mock): void {
            $mock->shouldReceive('assertSafe')->once();
        });

        $result = app(NewsContentExtractor::class)->extract($profileUrl, 1);

        $this->assertSame('x_profile_timeline', $result['method']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('AK Parti Grup Toplantımızı gerçekleştirdik.', $result['items'][0]['title']);
        $this->assertSame('https://x.com/alitombastr/status/'.$statusId, $result['items'][0]['url']);
        $this->assertSame($imageUrl, $result['items'][0]['image_url']);
        $this->assertStringContainsString('Sultanbeyli için birlik', $result['items'][0]['body']);
        $this->assertStringNotContainsString('full_text', $result['items'][0]['body']);
        $this->assertStringNotContainsString('https://t.co/', $result['items'][0]['body']);
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
