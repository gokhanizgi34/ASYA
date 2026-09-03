<?php

namespace App\Jobs;

use App\CampaignStatus;
use App\Models\ScheduleEntry;
use App\PublicationStatus;
use App\ScheduleAction;
use App\ScheduleStatus;
use App\Services\CampaignWorkflow;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessScheduleEntry implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [300, 300];

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public int $scheduleEntryId) {}

    public function handle(CampaignWorkflow $workflow): void
    {
        $entry = DB::transaction(function (): ?ScheduleEntry {
            $locked = ScheduleEntry::query()->with(['publication', 'campaign'])->lockForUpdate()->find($this->scheduleEntryId);
            if (! $locked || $locked->status !== ScheduleStatus::Pending || $locked->scheduled_for->isFuture()) {
                return null;
            }
            $locked->update(['status' => ScheduleStatus::Processing, 'attempt_count' => $locked->attempt_count + 1, 'started_at' => now(), 'completed_at' => null, 'failure_message' => null]);

            return $locked;
        }, 3);
        if (! $entry) {
            return;
        }

        try {
            match ($entry->action) {
                ScheduleAction::PublishWordPress => $this->dispatchPublication($entry),
                ScheduleAction::ActivateCampaign => $workflow->transitionCampaign($entry->campaign, CampaignStatus::Active),
                ScheduleAction::CompleteCampaign => $workflow->transitionCampaign($entry->campaign, CampaignStatus::Completed),
            };
            $entry->update(['status' => ScheduleStatus::Completed, 'active_key' => null, 'completed_at' => now()]);
        } catch (Throwable $exception) {
            $entry->update(['status' => ScheduleStatus::Failed, 'failure_message' => str($exception->getMessage())->limit(1000)->toString(), 'completed_at' => now()]);
            report($exception);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->scheduleEntryId;
    }

    private function dispatchPublication(ScheduleEntry $entry): void
    {
        $publication = $entry->publication;
        if (! $publication || ! in_array($publication->status, [PublicationStatus::Queued, PublicationStatus::Failed], true)) {
            throw new \RuntimeException('Yayın kaydı artık gönderime uygun değil.');
        }
        if ($publication->status === PublicationStatus::Failed) {
            $publication->update(['status' => PublicationStatus::Queued, 'failure_message' => null, 'queued_at' => now(), 'started_at' => null, 'completed_at' => null]);
        }
        PublishArticleToWordPress::dispatch($publication->id)->onQueue('publishing');
    }
}
