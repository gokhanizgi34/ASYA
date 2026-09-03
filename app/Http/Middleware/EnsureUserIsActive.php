<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();
        $agencyIsInactive = $user?->agency_id !== null && ! $user->agency?->hasActiveSubscription();

        if ($user && (! $user->is_active || $agencyIsInactive)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Hesabınız veya bağlı ajansınız pasif durumdadır.',
            ]);
        }

        return $next($request);
    }
}
