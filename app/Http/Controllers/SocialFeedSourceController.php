<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSocialFeedSourceRequest;
use App\Models\SocialFeedImport;
use App\Models\SocialFeedSource;
use App\Models\SocialListeningWatch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SocialFeedSourceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SocialFeedSource::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('social-feeds.index', [
            'sources' => SocialFeedSource::query()->visibleTo($user)->with(['watch', 'imports' => fn ($query) => $query->latest()->limit(3)])->latest()->get(),
            'imports' => SocialFeedImport::query()->visibleTo($user)->with('source')->latest()->limit(20)->get(),
            'watches' => SocialListeningWatch::query()->visibleTo($user)->where('is_active', true)->get(),
            'platforms' => ['x', 'instagram', 'facebook', 'youtube', 'tiktok', 'linkedin', 'web'],
        ]);
    }

    public function store(StoreSocialFeedSourceRequest $request): RedirectResponse
    {
        $watch = SocialListeningWatch::findOrFail($request->integer('social_listening_watch_id'));

        SocialFeedSource::create([
            'agency_id' => $watch->agency_id,
            'social_listening_watch_id' => $watch->id,
            'created_by' => $request->user()?->id,
            ...$request->safe()->except('social_listening_watch_id'),
            'social_listening_watch_id' => $watch->id,
            'source_type' => 'json_manual',
            'field_map' => [
                'external_id' => 'id',
                'content' => 'text',
                'author_handle' => 'author',
                'url' => 'url',
                'published_at' => 'published_at',
                'engagement_count' => 'engagement',
            ],
        ]);

        return back()->with('success', 'Sosyal akış kaynağı oluşturuldu.');
    }
}
