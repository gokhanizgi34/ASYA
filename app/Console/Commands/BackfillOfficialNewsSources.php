<?php

namespace App\Console\Commands;

use App\Models\NewsSource;
use App\Services\AutomaticNewsPipelineStarter;
use App\Services\NewsFeedImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('news:backfill-official {--days=3 : Geriye dönük gün sayısı} {--source=* : Yalnızca belirtilen kaynak kimliklerini işle}')]
#[Description('Resmi kurum kaynaklarından bir kerelik geriye dönük haber alımı yapar')]
class BackfillOfficialNewsSources extends Command
{
    public function handle(NewsFeedImporter $importer, AutomaticNewsPipelineStarter $pipeline): int
    {
        $days = min(30, max(1, (int) $this->option('days')));
        $sourceIds = collect($this->option('source'))->filter()->map(fn (mixed $id): int => (int) $id);
        $sources = NewsSource::query()
            ->where('is_active', true)
            ->where('source_type', 'official')
            ->when($sourceIds->isNotEmpty(), fn ($query) => $query->whereKey($sourceIds))
            ->orderBy('id')
            ->get();
        $imported = 0;

        foreach ($sources as $source) {
            try {
                $result = $importer->import($source, $days);
                $pipeline->start($source, $result['item_ids']);
                $imported += $result['imported'];
                $this->info($source->name.': '.$result['imported'].' yeni haber alındı.');
            } catch (Throwable $exception) {
                $this->error($source->name.': '.$exception->getMessage());
            }
        }

        $this->info($imported.' resmi kurum haberi ham haber havuzuna alındı.');

        return self::SUCCESS;
    }
}
