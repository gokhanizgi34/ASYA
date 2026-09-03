<?php

namespace App\Console\Commands;

use App\Jobs\AggregateAgencyAnalytics;
use App\Models\Agency;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:aggregate {--date= : YYYY-MM-DD rapor tarihi} {--agency=* : Ajans kimlikleri}')]
#[Description('Günlük ajans analitik snapshot işlerini kuyruğa gönderir')]
class AggregateAnalytics extends Command
{
    public function handle(): int
    {
        $date = CarbonImmutable::parse($this->option('date') ?: now()->toDateString())->toDateString();
        $ids = collect($this->option('agency'))->filter()->map(fn (mixed $id): int => (int) $id);
        $query = Agency::query()->where('is_active', true)->when($ids->isNotEmpty(), fn ($query) => $query->whereKey($ids));
        $count = 0;
        $query->orderBy('id')->pluck('id')->each(function (int $agencyId) use ($date, &$count): void {
            AggregateAgencyAnalytics::dispatch($agencyId, $date)->onQueue('analytics');
            $count++;
        });
        $this->info($count.' analitik snapshot işi kuyruğa alındı.');

        return self::SUCCESS;
    }
}
