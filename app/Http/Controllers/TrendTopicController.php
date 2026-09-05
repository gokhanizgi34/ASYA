<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\RawNewsItem;
use App\Models\TrendTopic;
use App\Models\User;
use App\Services\SystemSettings;
use App\TrendStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TrendTopicController extends Controller
{
    public function index(Request $request, SystemSettings $settings): View
    {
        Gate::authorize('viewAny', TrendTopic::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $status = (string) $request->query('status', '');
        $provider = $request->query('provider') === 'x' ? 'x' : 'google';
        $providerName = $provider === 'x' ? 'X Gündemi' : 'Google Trends';
        $query = TrendTopic::query()->visibleTo($user)->with('agency')->where('context->provider', $providerName);
        if (TrendStatus::tryFrom($status)) {
            $query->where('status', $status);
        }
        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->query('q')).'%');
        }

        $agencies = $this->agencies($user);
        $settingsAgencyId = $user->isSystemAdministrator()
            ? ($agencies->contains('id', $request->integer('agency_id')) ? $request->integer('agency_id') : $agencies->first()?->id)
            : $user->agency_id;
        $googleTrendDailyLimit = (int) $settings->get('trends.google_daily_item_limit', $settingsAgencyId);
        $googleTrendUsedToday = $settingsAgencyId === null ? 0 : RawNewsItem::query()
            ->where('agency_id', $settingsAgencyId)
            ->where('external_id', 'like', 'google-trends-%')
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();
        $xTrendDailyLimit = (int) $settings->get('trends.x_daily_item_limit', $settingsAgencyId);
        $xTrendUsedToday = $settingsAgencyId === null ? 0 : RawNewsItem::query()
            ->where('agency_id', $settingsAgencyId)
            ->where('external_id', 'like', 'x-trend-%')
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();
        $providerCounts = [
            'google' => TrendTopic::query()->visibleTo($user)->where('context->provider', 'Google Trends')->count(),
            'x' => TrendTopic::query()->visibleTo($user)->where('context->provider', 'X Gündemi')->count(),
        ];
        $filteredTopics = TrendTopic::query()->visibleTo($user)->where('context->provider', $providerName);

        return view('trends.index', [
            'topics' => $query->orderByDesc('score')->orderByDesc('last_seen_at')->paginate(20)->withQueryString(),
            'statuses' => TrendStatus::cases(),
            'statusCounts' => (clone $filteredTopics)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'provider' => $provider,
            'providerCounts' => $providerCounts,
            'agencies' => $agencies,
            'settingsAgencyId' => $settingsAgencyId,
            'googleTrendDailyLimit' => $googleTrendDailyLimit,
            'googleTrendUsedToday' => $googleTrendUsedToday,
            'xTrendDailyLimit' => $xTrendDailyLimit,
            'xTrendUsedToday' => $xTrendUsedToday,
            'lastAnalyzedAt' => (clone $filteredTopics)->max('analyzed_at'),
        ]);
    }

    public function show(TrendTopic $trendTopic): View
    {
        Gate::authorize('view', $trendTopic);

        return view('trends.show', [
            'topic' => $trendTopic->load('agency'),
            'snapshots' => $trendTopic->snapshots()->orderBy('period_end')->limit(96)->get(),
        ]);
    }

    /** @return Collection<int, Agency> */
    private function agencies(User $user): Collection
    {
        return Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get();
    }
}
