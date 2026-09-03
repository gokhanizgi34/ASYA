<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSocialListeningWatchRequest;
use App\Models\Agency;
use App\Models\SocialListeningWatch;
use App\Models\SocialMention;
use App\Models\User;
use App\SocialMentionStatus;
use App\SocialSentiment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SocialListeningController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SocialListeningWatch::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $mentionsQuery = SocialMention::query()
            ->visibleTo($user)
            ->with('watch')
            ->when($request->filled('platform'), fn ($query) => $query->where('platform', $request->string('platform')))
            ->when($request->filled('sentiment'), fn ($query) => $query->where('sentiment', $request->string('sentiment')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')));

        $summaryQuery = SocialMention::query()->visibleTo($user);

        return view('social-listening.index', [
            'watches' => SocialListeningWatch::query()->visibleTo($user)->withCount('mentions')->latest()->get(),
            'mentions' => $mentionsQuery->latest('published_at')->paginate(20)->withQueryString(),
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'negative' => (clone $summaryQuery)->where('sentiment', SocialSentiment::Negative)->count(),
                'urgent' => (clone $summaryQuery)->where('urgency_score', '>=', 70)->count(),
                'open' => (clone $summaryQuery)->whereIn('status', [SocialMentionStatus::New, SocialMentionStatus::Reviewing])->count(),
            ],
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->get(),
            'platforms' => ['x', 'instagram', 'facebook', 'youtube', 'tiktok', 'linkedin', 'web'],
            'sentiments' => SocialSentiment::cases(),
            'statuses' => SocialMentionStatus::cases(),
        ]);
    }

    public function store(StoreSocialListeningWatchRequest $request): RedirectResponse
    {
        SocialListeningWatch::create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Sosyal dinleme kuralı oluşturuldu.');
    }
}
