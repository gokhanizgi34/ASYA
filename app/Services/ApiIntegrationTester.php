<?php

namespace App\Services;

use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\Models\ApiIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ApiIntegrationTester
{
    public function __construct(
        private readonly ExternalUrlGuard $urlGuard,
        private readonly RouteMethodLearner $routeMethodLearner,
    ) {}

    public function test(ApiIntegration $integration): bool
    {
        $startedAt = hrtime(true);

        try {
            if ($integration->provider === IntegrationProvider::GitHubModels) {
                $integration->update([
                    'last_tested_at' => now(),
                    'last_status_code' => 410,
                    'last_response_time_ms' => 0,
                    'last_error' => 'GitHub Models 30.07.2026 tarihinde emekliye ayrıldı ve artık kullanılamaz.',
                    'is_active' => false,
                ]);

                return false;
            }

            $testUrl = $integration->provider === IntegrationProvider::XTrends
                ? rtrim($integration->base_url, '/').'/'.(int) config('services.external_trends.x_woeid', 23424969)
                : ($integration->provider === IntegrationProvider::GitHubModels ? rtrim($integration->base_url, '/').'/chat/completions' : $integration->base_url);

            if ($integration->provider === IntegrationProvider::GoogleGemini) {
                $root = preg_replace('~/models(?:\\?.*)?$~', '', rtrim($integration->base_url, '/')) ?: '';
                $testUrl = $root.'/models/'.rawurlencode((string) $integration->model).':generateContent';
            }

            $this->urlGuard->assertSafe($testUrl);

            if ($integration->provider === IntegrationProvider::GoogleGemini) {
                $response = $this->request($integration)->post($testUrl, [
                    'generationConfig' => ['responseMimeType' => 'application/json', 'maxOutputTokens' => 2],
                    'contents' => [['role' => 'user', 'parts' => [['text' => 'Yalnızca {"ok":true} JSON yanıtı ver.']]]],
                ]);
            } elseif ($integration->provider === IntegrationProvider::GitHubModels) {
                $response = $this->request($integration)->post($testUrl, ['model' => $integration->model, 'messages' => [['role' => 'user', 'content' => 'Reply with OK.']], 'max_tokens' => 2]);
            } elseif ($integration->provider === IntegrationProvider::Pixabay) {
                $response = $this->request($integration)->get($testUrl, [
                    'q' => 'food',
                    'image_type' => 'photo',
                    'orientation' => 'horizontal',
                    'safesearch' => 'true',
                    'per_page' => 3,
                ]);
            } else {
                $response = $this->request($integration)->get($testUrl, $integration->provider === IntegrationProvider::XTrends ? ['max_trends' => 1] : []);
            }
            $elapsed = $this->elapsedMilliseconds($startedAt);
            $successful = $response->successful() || $response->redirect();

            $integration->update([
                'last_tested_at' => now(),
                'last_status_code' => $response->status(),
                'last_response_time_ms' => $elapsed,
                'last_error' => $successful ? null : $this->errorMessage($response),
            ]);
            $this->routeMethodLearner->observe(
                $integration->agency_id,
                $integration->base_url,
                'GET',
                $response->status(),
                $integration->name.' bağlantı testi',
            );

            return $successful;
        } catch (Throwable $exception) {
            $integration->update([
                'last_tested_at' => now(),
                'last_status_code' => null,
                'last_response_time_ms' => $this->elapsedMilliseconds($startedAt),
                'last_error' => Str::limit($exception->getMessage(), 1000, '…'),
            ]);

            if ($exception instanceof ConnectionException) {
                $this->routeMethodLearner->observe(
                    $integration->agency_id,
                    $integration->base_url,
                    'GET',
                    null,
                    $integration->name.' bağlantı testi',
                );
            }

            return false;
        }
    }

    private function request(ApiIntegration $integration): PendingRequest
    {
        $request = Http::connectTimeout(min(5, $integration->timeout_seconds))
            ->timeout($integration->timeout_seconds)
            ->acceptJson();

        if ($integration->provider === IntegrationProvider::GoogleGemini) {
            return $request->withQueryParameters(['key' => (string) $integration->credential]);
        }

        if ($integration->provider === IntegrationProvider::Pixabay) {
            return $request->withQueryParameters(['key' => (string) $integration->credential]);
        }

        if ($integration->provider === IntegrationProvider::Anthropic) {
            return $request
                ->withHeader('x-api-key', (string) $integration->credential)
                ->withHeader('anthropic-version', '2023-06-01');
        }

        return match ($integration->auth_type) {
            IntegrationAuthType::None => $request,
            IntegrationAuthType::Bearer => $request->withToken((string) $integration->credential),
            IntegrationAuthType::Basic => $request->withBasicAuth((string) $integration->username, (string) $integration->credential),
            IntegrationAuthType::ApiKeyHeader => $request->withHeader((string) $integration->api_key_header, (string) $integration->credential),
        };
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) && $message !== ''
            ? 'Gemini: '.Str::limit($message, 900)
            : 'Uzak servis HTTP '.$response->status().' durum kodu döndürdü.';
    }
}
