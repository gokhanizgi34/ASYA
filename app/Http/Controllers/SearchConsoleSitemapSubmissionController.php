<?php

namespace App\Http\Controllers;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

class SearchConsoleSitemapSubmissionController extends Controller
{
    public function __invoke(ApiIntegration $apiIntegration, GoogleSearchConsoleService $searchConsole): RedirectResponse
    {
        Gate::authorize('update', $apiIntegration);
        abort_unless($apiIntegration->provider === IntegrationProvider::GoogleSearchConsole, 404);
        abort_unless($apiIntegration->is_active, 422, 'Pasif Search Console entegrasyonu kullanılamaz.');

        $startedAt = hrtime(true);

        try {
            $response = $searchConsole->submitSitemap($apiIntegration);
            $apiIntegration->update([
                'last_tested_at' => now(),
                'last_status_code' => $response->status(),
                'last_response_time_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
                'last_error' => null,
            ]);

            return back()->with('success', 'Haber site haritası Google Search Console’a gönderildi.');
        } catch (Throwable $exception) {
            $message = Str::limit($exception->getMessage(), 1000, '…');
            $apiIntegration->update([
                'last_tested_at' => now(),
                'last_status_code' => null,
                'last_response_time_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
                'last_error' => $message,
            ]);

            report($exception);

            return back()->withErrors(['search_console' => 'Site haritası gönderilemedi: '.$message]);
        }
    }
}
