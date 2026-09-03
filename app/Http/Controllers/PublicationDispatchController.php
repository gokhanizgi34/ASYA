<?php

namespace App\Http\Controllers;

use App\Jobs\PublishArticleToWordPress;
use App\Models\Publication;
use App\PublicationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PublicationDispatchController extends Controller
{
    public function __invoke(Publication $publication): RedirectResponse
    {
        Gate::authorize('update', $publication);
        abort_unless($publication->status === PublicationStatus::Failed, 422, 'Yalnızca başarısız yayınlar yeniden kuyruğa alınabilir.');
        abort_unless($publication->publishingTarget()->where('is_active', true)->exists(), 422, 'Yayın hedefi aktif değil.');

        $publication->forceFill([
            'status' => PublicationStatus::Queued,
            'failure_message' => null,
            'queued_at' => now(),
            'started_at' => null,
            'completed_at' => null,
        ])->save();

        PublishArticleToWordPress::dispatch($publication->id)->onQueue('publishing')->afterCommit();

        return back()->with('success', 'Yayın yeniden kuyruğa alındı.');
    }
}
