<?php

namespace Tests\Feature;

use App\Services\RenderedPageCapture;
use Tests\TestCase;

class RenderedPageCaptureTest extends TestCase
{
    public function test_sanitized_local_html_is_rendered_as_png_without_remote_page_execution(): void
    {
        $chromeCandidates = [
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ];

        if (collect($chromeCandidates)->doesntContain(fn (string $path): bool => is_file($path))) {
            $this->markTestSkipped('Bu ortamda Chrome/Chromium bulunmuyor.');
        }

        $capture = app(RenderedPageCapture::class);
        $path = $capture->capture('<html><body><script>document.body.innerHTML="çalışmamalı";</script><h1>ASYA güvenli haber görüntüsü</h1><p>Bu metin yerel ve temizlenmiş HTML üzerinden görüntülenir.</p></body></html>');

        try {
            $this->assertFileExists($path);
            $this->assertSame("\x89PNG\r\n\x1a\n", file_get_contents($path, false, null, 0, 8));
        } finally {
            $capture->remove($path);
        }

        $this->assertFileDoesNotExist($path);
    }
}
