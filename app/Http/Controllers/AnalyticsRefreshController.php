<?php

namespace App\Http\Controllers;

use App\Jobs\AggregateAgencyAnalytics;
use App\Models\Agency;
use App\Models\AnalyticsSnapshot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AnalyticsRefreshController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Gate::authorize('create', AnalyticsSnapshot::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validate(['agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)]]);
        $query = Agency::query()->where('is_active', true);
        if ($user->isSystemAdministrator()) {
            $query->when($data['agency_id'] ?? null, fn ($query, $id) => $query->whereKey($id));
        } else {
            $query->whereKey($user->agency_id);
        }
        $query->pluck('id')->each(fn (int $agencyId) => AggregateAgencyAnalytics::dispatch($agencyId, now()->toDateString())->onQueue('analytics'));

        return back()->with('success', 'Güncel analitik toplama kuyruğa alındı.');
    }
}
