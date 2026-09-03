<?php

namespace App\Http\Controllers;

use App\Jobs\PublishSocialPost;
use App\Models\SocialPost;
use App\SocialPostStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SocialPostDispatchController extends Controller
{
    public function __invoke(SocialPost $socialPost): RedirectResponse
    {
        Gate::authorize('update', $socialPost);

        if (in_array($socialPost->status, [SocialPostStatus::Queued, SocialPostStatus::Published], true)) {
            throw ValidationException::withMessages(['status' => 'Bu gönderi zaten kuyrukta veya yayımlanmış.']);
        }

        $socialPost->update([
            'status' => SocialPostStatus::Queued,
            'error_message' => null,
        ]);

        $pending = PublishSocialPost::dispatch($socialPost->id);

        if ($socialPost->scheduled_for?->isFuture()) {
            $pending->delay($socialPost->scheduled_for);
        }

        return back()->with('success', $socialPost->scheduled_for?->isFuture() ? 'Gönderi planlanan zaman için kuyruğa alındı.' : 'Gönderi yayın kuyruğuna alındı.');
    }
}
