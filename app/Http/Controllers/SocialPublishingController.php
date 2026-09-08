<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSocialPostRequest;
use App\Http\Requests\StoreSocialPublishingAccountRequest;
use App\Http\Requests\UpdateSocialPublishingAccountRequest;
use App\Models\Agency;
use App\Models\Article;
use App\Models\SocialPost;
use App\Models\SocialPublishingAccount;
use App\Models\User;
use App\Services\XOAuth2Service;
use App\SocialPostStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SocialPublishingController extends Controller
{
    public function index(Request $request, XOAuth2Service $oauth): View
    {
        Gate::authorize('viewAny', SocialPost::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('social-publishing.index', [
            'accounts' => SocialPublishingAccount::query()->visibleTo($user)->withCount('posts')->get(),
            'posts' => SocialPost::query()->visibleTo($user)->with(['account', 'article'])->latest()->paginate(20),
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->get(),
            'articles' => Article::query()->visibleTo($user)->latest()->limit(100)->get(),
            'xOAuthConfigured' => $oauth->configured(),
            'xOAuthCallbackUrl' => $oauth->callbackUrl(),
        ]);
    }

    public function storeAccount(StoreSocialPublishingAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        SocialPublishingAccount::create([
            'agency_id' => $data['agency_id'],
            'created_by' => $request->user()?->id,
            'name' => $data['name'],
            'platform' => $data['platform'],
            'account_handle' => $data['account_handle'],
            'access_token' => $data['access_token'],
            'api_key' => $data['api_key'] ?? null,
            'api_secret' => $data['api_secret'] ?? null,
            'access_token_secret' => $data['access_token_secret'] ?? null,
            'publish_mode' => $data['platform'] === 'x' ? 'x_api_v2' : 'local_sandbox',
            'mention_rules' => $this->mentionRules((string) ($data['mention_rules_text'] ?? '')),
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', $data['platform'] === 'x' ? 'X yayın hesabı canlı API için kaydedildi.' : 'Sosyal yayın hesabı güvenli kasaya eklendi.');
    }

    public function updateAccount(UpdateSocialPublishingAccountRequest $request, SocialPublishingAccount $socialPublishingAccount): RedirectResponse
    {
        $data = $request->validated();
        $attributes = [
            'name' => $data['name'],
            'account_handle' => $data['account_handle'],
            'mention_rules' => $this->mentionRules((string) ($data['mention_rules_text'] ?? '')),
            'is_active' => $data['is_active'],
            'publish_mode' => $socialPublishingAccount->platform === 'x' ? 'x_api_v2' : $socialPublishingAccount->publish_mode,
        ];

        foreach (['access_token', 'api_key', 'api_secret', 'access_token_secret'] as $credential) {
            if (filled($data[$credential] ?? null)) {
                $attributes[$credential] = $data[$credential];
            }
        }

        $socialPublishingAccount->update($attributes);

        return back()->with('success', 'X yayın hesabı ve belediye etiketleri güncellendi.');
    }

    public function storePost(StoreSocialPostRequest $request): RedirectResponse
    {
        $account = SocialPublishingAccount::findOrFail($request->integer('social_publishing_account_id'));

        SocialPost::create([
            'agency_id' => $account->agency_id,
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'status' => SocialPostStatus::Draft,
        ]);

        return back()->with('success', 'Sosyal gönderi taslağı oluşturuldu.');
    }

    /** @return array<string, string> */
    private function mentionRules(string $value): array
    {
        return collect(preg_split('/\R/u', $value) ?: [])
            ->map(fn (string $line): array => array_map('trim', explode('=', $line, 2)))
            ->filter(fn (array $parts): bool => count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '')
            ->mapWithKeys(fn (array $parts): array => [$parts[0] => '@'.ltrim($parts[1], '@')])
            ->all();
    }
}
