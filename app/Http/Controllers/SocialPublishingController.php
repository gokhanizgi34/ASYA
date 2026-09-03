<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSocialPostRequest;
use App\Http\Requests\StoreSocialPublishingAccountRequest;
use App\Models\Agency;
use App\Models\Article;
use App\Models\SocialPost;
use App\Models\SocialPublishingAccount;
use App\Models\User;
use App\SocialPostStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SocialPublishingController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SocialPost::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('social-publishing.index', [
            'accounts' => SocialPublishingAccount::query()->visibleTo($user)->withCount('posts')->get(),
            'posts' => SocialPost::query()->visibleTo($user)->with(['account', 'article'])->latest()->paginate(20),
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->get(),
            'articles' => Article::query()->visibleTo($user)->latest()->limit(100)->get(),
        ]);
    }

    public function storeAccount(StoreSocialPublishingAccountRequest $request): RedirectResponse
    {
        SocialPublishingAccount::create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'publish_mode' => 'local_sandbox',
        ]);

        return back()->with('success', 'Sosyal yayın hesabı güvenli kasaya eklendi.');
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
}
