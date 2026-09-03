<?php

namespace App\Jobs;

use App\Models\Agency;
use App\Services\TrendAnalyzer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeAgencyTrends implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 900;

    public function __construct(public int $agencyId) {}

    public function handle(TrendAnalyzer $analyzer): void
    {
        if (! Agency::query()->whereKey($this->agencyId)->where('is_active', true)->exists()) {
            return;
        }

        $analyzer->analyze($this->agencyId);
    }

    public function uniqueId(): string
    {
        return (string) $this->agencyId;
    }
}
