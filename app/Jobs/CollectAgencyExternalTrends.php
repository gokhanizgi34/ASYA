<?php

namespace App\Jobs;

use App\Models\Agency;
use App\Services\ExternalTrendCollector;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CollectAgencyExternalTrends implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 900;

    /** @var array<int, int> */
    public array $backoff = [300, 300];

    public function __construct(public readonly int $agencyId) {}

    public function handle(ExternalTrendCollector $collector): void
    {
        if (! Agency::query()->whereKey($this->agencyId)->where('is_active', true)->exists()) {
            return;
        }

        $collector->collect($this->agencyId);
    }

    public function uniqueId(): string
    {
        return (string) $this->agencyId;
    }
}
