<?php

namespace App\Console\Commands;

use App\Jobs\CollectAgencyExternalTrends;
use App\Models\Agency;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trends:collect {--agency=* : Yalnızca belirtilen ajans kimliklerini işle}')]
#[Description('Google Trends ve yapılandırılmışsa X gündemlerini otomatik haber bandına gönderir')]
class CollectExternalTrends extends Command
{
    public function handle(): int
    {
        $agencyIds = collect($this->option('agency'))->filter()->map(fn (mixed $id): int => (int) $id);
        $query = Agency::query()->where('is_active', true);

        if ($agencyIds->isNotEmpty()) {
            $query->whereKey($agencyIds);
        }

        $count = 0;

        $query->orderBy('id')->pluck('id')->each(function (int $agencyId) use (&$count): void {
            CollectAgencyExternalTrends::dispatch($agencyId)->onQueue('analytics');
            $count++;
        });

        $this->info($count.' ajans için dış trend toplama işi kuyruğa alındı.');

        return self::SUCCESS;
    }
}
