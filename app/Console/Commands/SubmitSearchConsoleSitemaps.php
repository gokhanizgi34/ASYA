<?php

namespace App\Console\Commands;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

#[Signature('app:submit-search-console-sitemaps')]
#[Description('Aktif ajansların haber site haritalarını Google Search Console’a gönderir')]
class SubmitSearchConsoleSitemaps extends Command
{
    public function handle(GoogleSearchConsoleService $searchConsole): int
    {
        $failed = 0;

        ApiIntegration::query()
            ->where('provider', IntegrationProvider::GoogleSearchConsole)
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (ApiIntegration $integration) use ($searchConsole, &$failed): void {
                $startedAt = hrtime(true);

                try {
                    $response = $searchConsole->submitSitemap($integration);
                    $integration->update([
                        'last_tested_at' => now(),
                        'last_status_code' => $response->status(),
                        'last_response_time_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
                        'last_error' => null,
                    ]);
                    $this->info($integration->agency?->name.': site haritası gönderildi.');
                } catch (Throwable $exception) {
                    $failed++;
                    $message = Str::limit($exception->getMessage(), 1000, '…');
                    $integration->update([
                        'last_tested_at' => now(),
                        'last_status_code' => null,
                        'last_response_time_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
                        'last_error' => $message,
                    ]);
                    $this->error($integration->agency?->name.': '.$message);
                    report($exception);
                }
            });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
