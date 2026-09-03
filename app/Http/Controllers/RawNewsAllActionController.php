<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAllRawNewsItemsRequest;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use App\Services\AutomaticNewsPipelineStarter;
use Illuminate\Http\RedirectResponse;

class RawNewsAllActionController extends Controller
{
    public function __invoke(UpdateAllRawNewsItemsRequest $request, AutomaticNewsPipelineStarter $pipeline): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $action = (string) $request->validated('action');
        $eligibleStatuses = $action === 'retry_all'
            ? [RawNewsStatus::Failed]
            : [RawNewsStatus::Pending, RawNewsStatus::Failed];
        $agencyIds = RawNewsItem::query()
            ->visibleTo($user)
            ->whereIn('status', $eligibleStatuses)
            ->distinct()
            ->pluck('agency_id');
        $eligibleCount = 0;
        $queuedCount = 0;

        foreach ($agencyIds as $agencyId) {
            $ids = RawNewsItem::query()
                ->visibleTo($user)
                ->where('agency_id', $agencyId)
                ->whereIn('status', $eligibleStatuses)
                ->orderBy('id')
                ->pluck('id');
            $eligibleCount += $ids->count();

            if ($action === 'retry_all' && $ids->isNotEmpty()) {
                RawNewsItem::query()->whereKey($ids)->update([
                    'status' => RawNewsStatus::Pending,
                    'failure_message' => null,
                    'processed_at' => null,
                    'updated_at' => now(),
                ]);
            }

            foreach ($ids->chunk(20) as $chunk) {
                $batch = $pipeline->startForAgency(
                    agencyId: (int) $agencyId,
                    rawNewsItemIds: $chunk->all(),
                    originLabel: $action === 'retry_all' ? 'Toplu yeniden deneme' : 'Ham haber havuzu',
                );
                $queuedCount += $batch?->total_items ?? 0;
            }
        }

        $message = $eligibleCount === 0
            ? 'İşleme uygun ham haber bulunamadı.'
            : "{$eligibleCount} haber değerlendirildi, {$queuedCount} haber gerçek üretim kuyruğuna alındı.";

        return redirect()->route('raw-news.index')->with('success', $message);
    }
}
