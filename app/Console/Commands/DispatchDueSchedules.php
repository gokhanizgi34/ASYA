<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduleEntry;
use App\Models\ScheduleEntry;
use App\ScheduleStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('schedules:run')]
#[Description('Zamanı gelen planları benzersiz kuyruk işlerine dönüştürür')]
class DispatchDueSchedules extends Command
{
    public function handle(): int
    {
        $count = 0;
        ScheduleEntry::query()->where('status', ScheduleStatus::Pending)->where('scheduled_for', '<=', now())->orderBy('id')->chunkById(200, function ($entries) use (&$count): void {
            foreach ($entries as $entry) {
                ProcessScheduleEntry::dispatch($entry->id)->onQueue('scheduling');
                $count++;
            }
        });
        $this->info($count.' plan yürütme kuyruğuna alındı.');

        return self::SUCCESS;
    }
}
