<?php

namespace App\Jobs;

use App\Models\NewsSource;
use App\Services\AutomaticNewsPipelineStarter;
use App\Services\NewsFeedImporter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ImportNewsSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $newsSourceId) {}

    public function handle(NewsFeedImporter $importer, AutomaticNewsPipelineStarter $pipeline): void
    {
        $source = NewsSource::query()->find($this->newsSourceId);

        if (! $source?->is_active) {
            return;
        }

        try {
            $result = $importer->import($source);
            $pipeline->start($source, $result['item_ids']);
        } catch (Throwable $exception) {
            $source->forceFill([
                'last_fetched_at' => now(),
                'last_fetch_error' => Str::limit($exception->getMessage(), 1000, ''),
            ])->save();

            throw $exception;
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->newsSourceId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [300, 300];
    }
}
