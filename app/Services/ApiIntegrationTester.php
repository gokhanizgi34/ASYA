<?php

namespace App\Services;

use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\Models\ApiIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
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
            $testUrl = $integration->provider === IntegrationProvider::XTrends
                ? rtrim($integration->base_url, '/').'/'.(int) config('services.external_trends.x_woeid', 23424969)
                : $integration->base_url;
            $this->urlGuard->assertSafe($testUrl);
            $response = $this->request($integration)->get($testUrl, $integration->provider === IntegrationProvider::XTrends ? ['max_trends' => 1] : []);
            $elapsed = $this->elapsedMilliseconds($startedAt);
            $successful = $response->successful() || $response->redirect();

            $integration->update([
                'last_tested_at' => now(),
                'last_status_code' => $response->status(),
                'last_response_time_ms' => $elapsed,
                'last_error' => $successful ? null : 'Uzak servis HTTP '.$response->status().' durum kodu döndürdü.',
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
}
