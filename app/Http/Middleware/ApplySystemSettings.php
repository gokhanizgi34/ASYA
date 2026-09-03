<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySystemSettings
{
    public function __construct(
        private readonly SystemSettings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $agencyId = $user instanceof User ? $user->agency_id : null;
        $locale = (string) $this->settings->get('app.locale', $agencyId);
        $timezone = (string) $this->settings->get('app.timezone', $agencyId);

        config([
            'app.name' => $this->settings->get('app.display_name', $agencyId),
            'app.locale' => $locale,
            'app.timezone' => $timezone,
        ]);
        app()->setLocale($locale);
        date_default_timezone_set($timezone);

        return $next($request);
    }
}
