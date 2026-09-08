<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\SocialPublishingAccount;
use App\Models\User;
use App\Services\XOAuth2Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

class XOAuthConnectionController extends Controller
{
    public function connect(Request $request, XOAuth2Service $oauth): RedirectResponse
    {
        Gate::authorize('create', SocialPublishingAccount::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (! $oauth->configured()) {
            return back()->with('error', 'X platform Client ID ve Client Secret henüz sunucuda tanımlanmadı.');
        }

        $agencyId = $this->agencyId($request, $user);
        $state = Str::random(64);
        $codeVerifier = Str::random(96);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $request->session()->put('x_oauth_connection', [
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'agency_id' => $agencyId,
            'created_at' => now()->timestamp,
        ]);

        return redirect()->away($oauth->authorizationUrl($state, $codeChallenge));
    }

    public function callback(Request $request, XOAuth2Service $oauth): RedirectResponse
    {
        Gate::authorize('create', SocialPublishingAccount::class);
        $session = $request->session()->pull('x_oauth_connection');

        if (! is_array($session)
            || ! is_string($session['state'] ?? null)
            || ! hash_equals($session['state'], (string) $request->query('state'))
            || now()->timestamp - (int) ($session['created_at'] ?? 0) > 600) {
            return redirect()->route('social-publishing.index')->with('error', 'X bağlantı doğrulaması geçersiz veya süresi dolmuş. Yeniden bağlanın.');
        }

        if ($request->filled('error')) {
            return redirect()->route('social-publishing.index')->with('error', 'X hesabı bağlantı izni verilmedi.');
        }

        $code = (string) $request->query('code');
        $codeVerifier = (string) ($session['code_verifier'] ?? '');
        $agencyId = (int) ($session['agency_id'] ?? 0);

        if ($code === '' || $codeVerifier === '' || $agencyId < 1) {
            return redirect()->route('social-publishing.index')->with('error', 'X bağlantı bilgileri eksik. Yeniden deneyin.');
        }

        try {
            $tokens = $oauth->exchangeAuthorizationCode($code, $codeVerifier);
            $xUser = $oauth->authenticatedUser((string) $tokens['access_token']);
            $account = SocialPublishingAccount::query()
                ->where('agency_id', $agencyId)
                ->where('platform', 'x')
                ->where(function ($query) use ($xUser): void {
                    $query->where('x_user_id', $xUser['id'])
                        ->orWhere('account_handle', '@'.$xUser['username']);
                })
                ->firstOrNew();

            $account->forceFill([
                'agency_id' => $agencyId,
                'created_by' => $request->user()?->id,
                'name' => $xUser['name'].' X Hesabı',
                'platform' => 'x',
                'account_handle' => '@'.$xUser['username'],
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds((int) $tokens['expires_in']),
                'x_user_id' => $xUser['id'],
                'auth_type' => 'oauth2_pkce',
                'api_key' => null,
                'api_secret' => null,
                'access_token_secret' => null,
                'publish_mode' => 'x_api_v2',
                'is_active' => true,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('social-publishing.index')->with('error', 'X hesabı bağlanamadı. X uygulama ayarlarını ve izinlerini kontrol edip yeniden deneyin.');
        }

        return redirect()->route('social-publishing.index')->with('success', $account->account_handle.' X hesabı güvenli biçimde bağlandı.');
    }

    private function agencyId(Request $request, User $user): int
    {
        if (! $user->isSystemAdministrator()) {
            abort_unless($user->agency_id !== null, 403);

            return (int) $user->agency_id;
        }

        $validated = $request->validate([
            'agency_id' => ['required', 'integer', 'exists:agencies,id'],
        ]);
        abort_unless(Agency::query()->whereKey($validated['agency_id'])->where('is_active', true)->exists(), 422);

        return (int) $validated['agency_id'];
    }
}
