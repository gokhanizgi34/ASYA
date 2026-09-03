<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class RenderedPageCapture
{
    public function capture(string $html): string
    {
        $chrome = $this->chromePath();

        if ($chrome === null) {
            throw new RuntimeException('Güvenli ekran görüntüsü için Chrome bulunamadı.');
        }

        $directory = storage_path('app/private/news-captures');
        File::ensureDirectoryExists($directory);

        $token = Str::uuid()->toString();
        $htmlPath = $directory.DIRECTORY_SEPARATOR.$token.'.html';
        $imagePath = $directory.DIRECTORY_SEPARATOR.$token.'.png';
        $profilePath = $directory.DIRECTORY_SEPARATOR.$token.'-profile';
        File::ensureDirectoryExists($profilePath);
        file_put_contents($htmlPath, $this->sanitize($html), LOCK_EX);

        try {
            $process = new Process([
                $chrome,
                '--headless=new',
                '--disable-gpu',
                '--hide-scrollbars',
                '--disable-extensions',
                '--disable-background-networking',
                '--disable-component-update',
                '--disable-crash-reporter',
                '--disable-breakpad',
                '--no-first-run',
                '--user-data-dir='.$profilePath,
                '--window-size=1440,5000',
                '--screenshot='.$imagePath,
                'file:///'.str_replace('\\', '/', $htmlPath),
            ]);
            $process->setTimeout(45);
            $process->mustRun();

            if (! is_file($imagePath) || filesize($imagePath) === 0) {
                throw new RuntimeException('Haber sayfası ekran görüntüsü oluşturulamadı.');
            }

            return $imagePath;
        } finally {
            @unlink($htmlPath);
            File::deleteDirectory($profilePath);
        }
    }

    public function remove(string $path): void
    {
        $captureRoot = realpath(storage_path('app/private/news-captures'));
        $realPath = realpath($path);

        if ($captureRoot && $realPath && str_starts_with($realPath, $captureRoot.DIRECTORY_SEPARATOR)) {
            @unlink($realPath);
        }
    }

    private function sanitize(string $html): string
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return '<!doctype html><html><body>'.e(strip_tags($html)).'</body></html>';
        }

        foreach (['script', 'iframe', 'frame', 'object', 'embed', 'link', 'base', 'style', 'img', 'video', 'audio', 'source'] as $tag) {
            $nodes = iterator_to_array($document->getElementsByTagName($tag));

            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = Str::lower($attribute->name);

                if (str_starts_with($name, 'on') || in_array($name, ['style', 'src', 'srcset', 'action', 'formaction'], true)) {
                    $element->removeAttribute($attribute->name);
                }
            }
        }

        return (string) $document->saveHTML();
    }

    private function chromePath(): ?string
    {
        $configured = config('news_ingestion.chrome_path');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        foreach ([
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
