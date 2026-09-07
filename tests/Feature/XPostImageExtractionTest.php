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
}
