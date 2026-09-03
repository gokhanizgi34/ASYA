<?php

namespace App\Jobs;

use App\ContentBatchStatus;
use App\Models\ContentBatch;
use App\Services\ContentBatchProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ProcessContentBatch implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var array<int, int> */
    public array $backoff = [300, 300];

    public int $uniqueFor = 600;

    public function __construct(public int $contentBatchId) {}

    public function handle(ContentBatchProcessor $processor): void
    {
        $processor->process($this->contentBatchId);
    }

    public function uniqueId(): string
    {
        return (string) $this->contentBatchId;
    }

    public function failed(?Throwable $exception): void
    {
        $batch = ContentBatch::query()->find($this->contentBatchId);

        if (! $batch || $batch->status === ContentBatchStatus::Completed) {
            return;
        }

        $batch->update([
            'status' => ContentBatchStatus::Failed,
            'failure_message' => Str::limit($exception?->getMessage() ?? 'Kuyruk işi tamamlanamadı.', 1900),
            'completed_at' => now(),
        ]);
    }
}
