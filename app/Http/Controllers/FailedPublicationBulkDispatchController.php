<?php

namespace App\Http\Controllers;

use App\Jobs\PublishArticleToWordPress;
use App\Models\Publication;
use App\Models\User;
use App\PublicationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FailedPublicationBulkDispatchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Gate::authorize('create', Publication::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $ids = Publication::query()
            ->visibleTo($user)
            ->where('status', PublicationStatus::Failed)
            ->where(fn ($query) => $query->whereNull('failure_message')->orWhere('failure_message', 'not like', '[KALICI]%'))
            ->whereHas('publishingTarget', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->pluck('id');

        DB::transaction(function () use ($ids): void {
            Publication::query()->whereKey($ids)->update([
                'status' => PublicationStatus::Queued,
                'failure_message' => null,
                'queued_at' => now(),
                'started_at' => null,
                'completed_at' => null,
            ]);
        }, 3);

        $ids->each(fn (int $id) => PublishArticleToWordPress::dispatch($id)->onQueue('publishing'));

        return back()->with('success', $ids->count().' hatalı yayın yeniden kuyruğa alındı.');
    }
}
