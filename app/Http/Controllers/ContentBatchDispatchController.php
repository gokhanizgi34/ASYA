<?php

namespace App\Http\Controllers;

use App\ContentBatchItemStatus;
use App\ContentBatchStatus;
use App\Jobs\ProcessContentBatch;
use App\Models\ContentBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ContentBatchDispatchController extends Controller
{
    public function __invoke(ContentBatch $contentBatch): RedirectResponse
    {
        Gate::authorize('update', $contentBatch);

        DB::transaction(function () use ($contentBatch): void {
            $batch = ContentBatch::query()->lockForUpdate()->findOrFail($contentBatch->id);
            $batch->items()->where('status', ContentBatchItemStatus::Failed)->update([
                'status' => ContentBatchItemStatus::Queued,
                'failure_message' => null,
                'completed_at' => null,
            ]);
            $batch->update([
                'status' => ContentBatchStatus::Queued,
                'failed_items' => 0,
                'failure_message' => null,
                'completed_at' => null,
            ]);
        }, 3);

        ProcessContentBatch::dispatch($contentBatch->id)->onQueue('content')->afterCommit();

        return back()->with('success', 'Üretim bandı yeniden kuyruğa gönderildi.');
    }
}
