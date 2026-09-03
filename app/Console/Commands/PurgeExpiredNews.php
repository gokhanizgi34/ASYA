<?php

namespace App\Console\Commands;

use App\Models\RawNewsItem;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('news:purge-expired')]
#[Description('İki günlük bekleme süresini dolduran haberleri havuzdan kaldırır')]
class PurgeExpiredNews extends Command
{
    public function handle(): int
    {
        $deleted = RawNewsItem::withTrashed()
            ->where(function ($query): void {
                $query->where('expires_at', '<=', now())
                    ->orWhere(function ($query): void {
                        $query->whereNull('expires_at')->where('created_at', '<=', now()->subDays(2));
                    });
            })
            ->forceDelete();

        $this->info($deleted.' süresi dolmuş haber havuzundan kaldırıldı.');

        return self::SUCCESS;
    }
}
