<?php

namespace App\Jobs;

use App\Models\Agency;
use App\Services\AnalyticsAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AggregateAgencyAnalytics implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public int $agencyId, public string $reportDate) {}

    public function handle(AnalyticsAggregator $aggregator): void
    {
        if (Agency::query()->whereKey($this->agencyId)->where('is_active', true)->exists()) {
            $aggregator->aggregate($this->agencyId, CarbonImmutable::parse($this->reportDate));
        }
    }

    public function uniqueId(): string
    {
        return $this->agencyId.':'.$this->reportDate;
    }
}
