<?php

namespace App;

enum ContentBatchItemStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Kuyrukta', self::Processing => 'İşleniyor', self::Completed => 'Tamamlandı', self::Failed => 'Hatalı', self::Skipped => 'Atlandı',
        };
    }
}
