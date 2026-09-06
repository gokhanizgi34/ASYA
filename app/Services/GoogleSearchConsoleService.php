<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSearchConsoleService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const WEBMASTERS_BASE_URL = 'https://www.googleapis.com/webmasters/v3';

    private const INSPECTION_URL = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

    private const SCOPE = 'https://www.googleapis.com/auth/webmasters';

    public function getSite(ApiIntegration $integration): Response
    {
        $this->assertConfigured($integration);

        return $this->request($integration)
            ->get(self::WEBMASTERS_BASE_URL.'/sites/'.rawurlencode((string) $integration->username))
            ->throw();
    }

    public function submitSitemap(ApiIntegration $integration): Response
    {
        $this->assertConfigured($integration);

        return $this->request($integration)
            ->put(self::WEBMASTERS_BASE_URL.'/sites/'.rawurlencode((string) $integration->username).'/sitemaps/'.rawurlencode((string) $integration->model))
            ->throw();
    }

    /** @return array<string, mixed> */
    public function inspectUrl(ApiIntegration $integration, string $url): array
    {
        $this->assertConfigured($integration);

        if (! $this->propertyCoversUrl((string) $integration->username, $url)) {
            throw new RuntimeException('Yayın adresi tanımlı Search Console mülkünün kapsamında değil.');
        }

        return $this->request($integration)
            ->post(self::INSPECTION_URL, [
                'inspectionUrl' => $url,
                'siteUrl' => (string) $integration->username,
                'languageCode' => 'tr-TR',
            ])
            ->throw()
            ->json();
    }

    private function request(ApiIntegration $integration): PendingRequest
    {
        return Http::acceptJson()
            ->withToken($this->accessToken($integration))
            ->connectTimeout(10)
            ->timeout(max(15, $integration->timeout_seconds));
    }

    private function accessToken(ApiIntegration $integration): string
    {
        $credentials = $this->credentials($integration);
        $cacheKey = 'search-console-token:'.$integration->id.':'.hash('sha256', $credentials['client_email'].$credentials['private_key']);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials, $integration): string {
            $now = now()->getTimestamp();
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));
            $unsignedToken = $header.'.'.$claims;
            $privateKey = openssl_pkey_get_private($credentials['private_key']);

            if ($privateKey === false || ! openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Google hizmet hesabı özel anahtarı imza üretmek için kullanılamadı.');
            }

            $response = Http::asForm()
                ->connectTimeout(10)
                ->timeout(max(15, $integration->timeout_seconds))
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $unsignedToken.'.'.$this->base64UrlEncode($signature),
                ])
                ->throw();

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('Google erişim jetonu alınamadı.');
            }

            return $token;
        });
    }

    /** @return array{client_email:string,private_key:string} */
    private function credentials(ApiIntegration $integration): array
    {
        $credentials = json_decode((string) $integration->credential, true);

        if (! is_array($credentials)
            || ($credentials['type'] ?? null) !== 'service_account'
            || ! is_string($credentials['client_email'] ?? null)
            || ! is_string($credentials['private_key'] ?? null)
            || $credentials['client_email'] === ''
            || $credentials['private_key'] === '') {
            throw new RuntimeException('Google hizmet hesabı JSON bilgisi geçersiz.');
        }

        return [
            'client_email' => $credentials['client_email'],
            'private_key' => $credentials['private_key'],
        ];
    }

    private function assertConfigured(ApiIntegration $integration): void
    {
        if ($integration->provider !== IntegrationProvider::GoogleSearchConsole
            || blank($integration->username)
            || blank($integration->model)
            || blank($integration->credential)) {
            throw new RuntimeException('Google Search Console entegrasyonu eksik yapılandırılmış.');
        }
    }

    private function propertyCoversUrl(string $property, string $url): bool
    {
        $urlHost = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($urlHost === '') {
            return false;
        }

        if (str_starts_with($property, 'sc-domain:')) {
            $domain = strtolower(substr($property, 10));

            return $urlHost === $domain || str_ends_with($urlHost, '.'.$domain);
        }

        return str_starts_with(rtrim($url, '/').'/', rtrim($property, '/').'/');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
