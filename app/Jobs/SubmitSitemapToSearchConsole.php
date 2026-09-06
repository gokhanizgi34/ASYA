<?php

namespace App\Jobs;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SubmitSitemapToSearchConsole implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public int $integrationId) {}

    public function uniqueId(): string
    {
        return (string) $this->integrationId;
    }

    public function handle(GoogleSearchConsoleService $searchConsole): void
    {
        $integration = ApiIntegration::query()
            ->where('provider', IntegrationProvider::GoogleSearchConsole)
            ->where('is_active', true)
            ->find($this->integrationId);

        if (! $integration) {
            return;
        }

        $startedAt = hrtime(true);

        try {
            $response = $searchConsole->submitSitemap($integration);
            $integration->update([
                'last_tested_at' => now(),
                'last_status_code' => $response->status(),
                'last_response_time_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $integration->update([
                'last_tested_at' => now(),
                'last_status_code' => null,
                'last_response_time_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
                'last_error' => str($exception->getMessage())->limit(1000)->toString(),
            ]);

            throw $exception;
        }
    }
}
