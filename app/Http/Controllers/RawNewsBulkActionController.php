<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUpdateRawNewsItemsRequest;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use Illuminate\Http\RedirectResponse;

class RawNewsBulkActionController extends Controller
{
    public function __invoke(BulkUpdateRawNewsItemsRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $ids = array_map('intval', $request->validated('items'));
        $query = RawNewsItem::query()->visibleTo($user)->whereKey($ids);
        abort_unless((clone $query)->count() === count($ids), 403);

        [$fromStatuses, $targetStatus] = match ($request->validated('action')) {
            'queue' => [[RawNewsStatus::Pending, RawNewsStatus::Review, RawNewsStatus::Failed], RawNewsStatus::Queued],
            'reject' => [[RawNewsStatus::Pending, RawNewsStatus::Review, RawNewsStatus::Queued, RawNewsStatus::Failed], RawNewsStatus::Rejected],
            'retry' => [[RawNewsStatus::Failed], RawNewsStatus::Pending],
        };

        $updates = [
            'status' => $targetStatus,
            'processed_at' => null,
            'updated_at' => now(),
        ];

        if ($targetStatus === RawNewsStatus::Pending) {
            $updates['failure_message'] = null;
        }

        $updated = $query->whereIn('status', $fromStatuses)->update($updates);

        return redirect()->route('raw-news.index')->with('success', "{$updated} ham haber güncellendi.");
    }
}
