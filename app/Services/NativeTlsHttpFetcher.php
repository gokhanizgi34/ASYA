<?php

namespace App\Services;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Symfony\Component\Process\Process;

class NativeTlsHttpFetcher
{
    private const MAX_BODY_BYTES = 5_000_000;

    public function __construct(private readonly ExternalUrlGuard $urlGuard) {}

    public function fetch(string $url, string $accept, string $userAgent, int $maxBodyBytes = self::MAX_BODY_BYTES): Response
    {
        $curl = $this->curlPath();

        if ($curl === null) {
            throw new RuntimeException('Sistem cURL istemcisi bulunamadı.');
        }

        $currentUrl = $url;

        for ($redirects = 0; $redirects <= 5; $redirects++) {
            $this->urlGuard->assertSafe($currentUrl);
            [$status, $headers, $body] = $this->request($curl, $currentUrl, $accept, $userAgent, min(20 * 1024 * 1024, max(1024, $maxBodyBytes)));

            if (! in_array($status, [301, 302, 303, 307, 308], true)) {
                return new Response(new PsrResponse($status, $headers, $body));
            }

            $location = $headers['Location'][0] ?? $headers['location'][0] ?? null;

            if (! is_string($location) || $location === '') {
                throw new RuntimeException('Kaynak yönlendirme adresi geçersiz.');
            }

            $nextUrl = $this->resolveUrl($currentUrl, $location);

            if (parse_url($currentUrl, PHP_URL_SCHEME) === 'https' && parse_url($nextUrl, PHP_URL_SCHEME) !== 'https') {
                throw new RuntimeException('Güvenli HTTPS bağlantısı HTTP adresine yönlendirilemez.');
            }

            $currentUrl = $nextUrl;
        }

        throw new RuntimeException('Kaynak izin verilenden fazla yönlendirme yaptı.');
    }

    /**
     * @return array{int, array<string, array<int, string>>, string}
     */
    private function request(string $curl, string $url, string $accept, string $userAgent, int $maxBodyBytes): array
    {
        $bodyPath = tempnam(sys_get_temp_dir(), 'asya-native-body-');
        $headerPath = tempnam(sys_get_temp_dir(), 'asya-native-header-');

        if ($bodyPath === false || $headerPath === false) {
            throw new RuntimeException('Güvenli haber alımı için geçici dosya oluşturulamadı.');
        }

        try {
            $process = new Process([
                $curl,
                '--silent',
                '--show-error',
                '--connect-timeout',
                '8',
                '--max-time',
                '20',
                '--max-filesize',
                (string) $maxBodyBytes,
                '--proto',
                '=http,https',
                '--header',
                'Accept: '.$accept,
                '--user-agent',
                $userAgent,
                '--output',
                $bodyPath,
                '--dump-header',
                $headerPath,
                '--write-out',
                '%{http_code}',
                $url,
            ]);
            $process->setTimeout(25);
            $process->mustRun();
            $status = (int) trim($process->getOutput());
            $bodySize = filesize($bodyPath);

            if ($status < 100 || $status > 599 || $bodySize === false || $bodySize > $maxBodyBytes) {
                throw new RuntimeException('Sistem cURL yanıtı geçersiz veya aşırı büyük.');
            }

            $body = file_get_contents($bodyPath);
            $rawHeaders = file_get_contents($headerPath);

            if ($body === false || $rawHeaders === false) {
                throw new RuntimeException('Sistem cURL yanıtı okunamadı.');
            }

            return [$status, $this->parseHeaders($rawHeaders), $body];
        } finally {
            @unlink($bodyPath);
            @unlink($headerPath);
        }
    }

    /** @return array<string, array<int, string>> */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];

        foreach (preg_split('/\r?\n/', trim($rawHeaders)) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, 'HTTP/') || ! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $headers[$name][] = $value;
        }

        return $headers;
    }

    private function resolveUrl(string $baseUrl, string $location): string
    {
        if (preg_match('~^https?://~i', $location) === 1) {
            return $location;
        }

        $scheme = (string) parse_url($baseUrl, PHP_URL_SCHEME);
        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        $port = parse_url($baseUrl, PHP_URL_PORT);
        $origin = $scheme.'://'.$host.($port ? ':'.$port : '');

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = (string) parse_url($baseUrl, PHP_URL_PATH);
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $origin.($directory === '' ? '' : $directory).'/'.$location;
    }

    private function curlPath(): ?string
    {
        $configured = config('news_ingestion.native_curl_path');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            foreach (['/usr/bin/curl', '/bin/curl'] as $candidate) {
                if (is_file($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
            }

            return null;
        }

        $systemRoot = getenv('SystemRoot');
        $candidate = is_string($systemRoot) ? $systemRoot.'\\System32\\curl.exe' : '';

        return $candidate !== '' && is_file($candidate) ? $candidate : null;
    }
}
