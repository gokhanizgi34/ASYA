<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeAgencyTrends;
use App\Models\Agency;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trends:analyze {--agency=* : Analiz edilecek ajans kimlikleri}')]
#[Description('Aktif ajansların trend analizlerini kuyruğa gönderir')]
class AnalyzeTrends extends Command
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
            AnalyzeAgencyTrends::dispatch($agencyId)->onQueue('analytics');
            $count++;
        });
        $this->info($count.' ajans trend analizi kuyruğa alındı.');

        return self::SUCCESS;
    }
}
