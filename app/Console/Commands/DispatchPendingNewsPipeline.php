<?php

namespace App\Console\Commands;

use App\ContentBatchItemStatus;
use App\Models\Agency;
use App\Models\ContentBatchItem;
use App\Models\RawNewsItem;
use App\RawNewsStatus;
use App\Services\AutomaticNewsPipelineStarter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('news:pipeline {--agency=* : Yalnızca belirtilen ajans kimliklerini işle}')]
#[Description('Bekleyen ve AI eksikliği nedeniyle başarısız haberleri otomatik üretim bandına alır')]
class DispatchPendingNewsPipeline extends Command
{
    public function handle(AutomaticNewsPipelineStarter $pipeline): int
    {
        $agencyIds = collect($this->option('agency'))->filter()->map(fn (mixed $id): int => (int) $id);
        $query = Agency::query()->where('is_active', true);

        if ($agencyIds->isNotEmpty()) {
            $query->whereKey($agencyIds);
        }

        $batchCount = 0;

        $query->orderBy('id')->each(function (Agency $agency) use ($pipeline, &$batchCount): void {
            $recoveredIds = $this->recoverStaleProcessingItems($agency);
            $ids = RawNewsItem::query()
                ->where('agency_id', $agency->id)
                ->where(function ($query): void {
                    $query->where('expires_at', '>', now())
                        ->orWhere(function ($query): void {
                            $query->whereNull('expires_at')->where('created_at', '>', now()->subDays(2));
                        });
                })
                ->where(function ($query): void {
                    $query->where('status', RawNewsStatus::Pending)
                        ->orWhere(function ($query): void {
                            $query->where('status', RawNewsStatus::Failed)
                                ->where('failure_message', 'like', '%yapay zekâ%');
                        });
                })
                ->orderByDesc('priority')
                ->orderBy('id')
                ->limit(20)
                ->pluck('id')
                ->merge($recoveredIds)
                ->unique()
                ->take(20)
                ->all();

            if ($pipeline->startForAgency($agency->id, $ids, 'Bekleyen haberler')) {
                $batchCount++;
            }
        });

        $this->info($batchCount.' otomatik üretim bandı başlatıldı.');

        return self::SUCCESS;
    }

    /** @return array<int, int> */
    private function recoverStaleProcessingItems(Agency $agency): array
    {
        return DB::transaction(function () use ($agency): array {
            $rawNewsItems = RawNewsItem::query()
                ->where('agency_id', $agency->id)
                ->where('status', RawNewsStatus::Processing)
                ->where('updated_at', '<=', now()->subMinutes(15))
                ->lockForUpdate()
                ->limit(20)
                ->get();

            if ($rawNewsItems->isEmpty()) {
                return [];
            }

            $ids = $rawNewsItems->modelKeys();
            $message = 'Kesintiye uğrayan işlem otomatik olarak yeniden kuyruğa alındı.';

            ContentBatchItem::query()
                ->whereIn('raw_news_item_id', $ids)
                ->where('status', ContentBatchItemStatus::Processing)
                ->update([
                    'status' => ContentBatchItemStatus::Failed,
                    'failure_message' => $message,
                    'completed_at' => now(),
                ]);

            RawNewsItem::query()->whereKey($ids)->update([
                'status' => RawNewsStatus::Failed,
                'failure_message' => $message,
            ]);

            return $ids;
        }, 3);
    }
}
