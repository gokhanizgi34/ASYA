<?php

namespace App\Http\Controllers;

use App\Models\ApiIntegration;
use App\Services\ApiIntegrationTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ApiIntegrationTestController extends Controller
{
    public function __invoke(ApiIntegration $apiIntegration, ApiIntegrationTester $tester): RedirectResponse
    {
        Gate::authorize('update', $apiIntegration);
        abort_unless($apiIntegration->is_active, 422, 'Pasif entegrasyon test edilemez.');

        $successful = $tester->test($apiIntegration);

        if (! $successful) {
            return back()->withErrors(['connection' => 'API bağlantısı doğrulanamadı: '.$apiIntegration->fresh()->last_error]);
        }

        return back()->with('success', 'API bağlantısı başarıyla doğrulandı.');
    }
}
