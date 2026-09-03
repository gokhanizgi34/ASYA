<?php

namespace App\Console\Commands;

use App\Jobs\ImportNewsSource;
use App\Models\NewsSource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('news:import {--source=* : Yalnızca belirtilen kaynak kimliklerini al}')]
#[Description('Aktif haber kaynaklarını Akıllı Alım kuyruğuna gönderir')]
class ImportActiveNewsSources extends Command
{
    public function handle(): int
    {
        $sourceIds = collect($this->option('source'))->filter()->map(fn (mixed $id): int => (int) $id);
        $query = NewsSource::query()->where('is_active', true);

        if ($sourceIds->isNotEmpty()) {
            $query->whereKey($sourceIds);
        }

        $count = 0;
        $query->orderBy('id')->pluck('id')->each(function (int $sourceId) use (&$count): void {
            ImportNewsSource::dispatch($sourceId)->onQueue('news-ingestion');
            $count++;
        });

        $this->info($count.' aktif haber kaynağı Akıllı Alım kuyruğuna gönderildi.');

        return self::SUCCESS;
    }
}
