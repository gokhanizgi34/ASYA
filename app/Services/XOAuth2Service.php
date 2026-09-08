<?php

namespace App\Services;

use App\Models\SocialPublishingAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XOAuth2Service
{
    /** @return array<int, string> */
    public function scopes(): array
    {
        return ['tweet.read', 'tweet.write', 'users.read', 'offline.access'];
    }

    public function configured(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    public function callbackUrl(): string
    {
        return (string) (config('services.x.redirect_uri') ?: route('x-oauth.callback'));
    }

    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        $this->ensureConfigured();

        return 'https://x.com/i/oauth2/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->callbackUrl(),
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<string, mixed> */
    public function exchangeAuthorizationCode(string $code, string $codeVerifier): array
    {
        $response = $this->tokenRequest()->post('https://api.x.com/2/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->callbackUrl(),
            'code_verifier' => $codeVerifier,
            'client_id' => $this->clientId(),
        ])->throw();

        return $this->validatedTokens($response->json());
    }

    /** @return array{id: string, name: string, username: string} */
    public function authenticatedUser(string $accessToken): array
    {
        $data = Http::acceptJson()
            ->withToken($accessToken)
            ->connectTimeout(5)
            ->timeout(20)
            ->get('https://api.x.com/2/users/me')
            ->throw()
            ->json('data');

        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! is_string($data['username'] ?? null)) {
            throw new RuntimeException('X, bağlanan hesap kimliğini doğrulayamadı.');
        }

        return [
            'id' => $data['id'],
            'name' => is_string($data['name'] ?? null) ? $data['name'] : $data['username'],
            'username' => $data['username'],
        ];
    }

    public function accessToken(SocialPublishingAccount $account): string
    {
        if (! $account->token_expires_at || $account->token_expires_at->isAfter(now()->addMinutes(5))) {
            return (string) $account->access_token;
        }

        if (blank($account->refresh_token)) {
            throw new RuntimeException('X bağlantısının süresi doldu. Hesabı yeniden bağlayın.');
        }

        return Cache::lock('x-oauth-refresh-'.$account->id, 30)->block(10, function () use ($account): string {
            $account->refresh();

            if ($account->token_expires_at?->isAfter(now()->addMinutes(5))) {
                return (string) $account->access_token;
            }

            $tokens = $this->refresh((string) $account->refresh_token);
            $account->forceFill([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
                'token_expires_at' => now()->addSeconds((int) $tokens['expires_in']),
            ])->save();

            return (string) $account->access_token;
        });
    }

    /** @return array<string, mixed> */
    private function refresh(string $refreshToken): array
    {
        $response = $this->tokenRequest()->post('https://api.x.com/2/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId(),
        ])->throw();

        return $this->validatedTokens($response->json());
    }

    private function tokenRequest(): PendingRequest
    {
        $this->ensureConfigured();

        return Http::asForm()
            ->acceptJson()
            ->withBasicAuth($this->clientId(), $this->clientSecret())
            ->connectTimeout(5)
            ->timeout(20);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTokens(mixed $payload): array
    {
        if (! is_array($payload) || ! is_string($payload['access_token'] ?? null)) {
            throw new RuntimeException('X geçerli bir kullanıcı erişim anahtarı döndürmedi.');
        }

        $payload['expires_in'] = max(60, (int) ($payload['expires_in'] ?? 7200));

        return $payload;
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('X platform bağlantısı henüz yapılandırılmadı.');
        }
    }

    private function clientId(): string
    {
        return trim((string) config('services.x.client_id'));
    }

    private function clientSecret(): string
    {
        return trim((string) config('services.x.client_secret'));
    }
}
