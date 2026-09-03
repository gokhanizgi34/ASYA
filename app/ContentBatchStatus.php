<?php

namespace App;

enum ContentBatchStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Kuyrukta', self::Processing => 'İşleniyor', self::Completed => 'Tamamlandı', self::Partial => 'Kısmen Tamamlandı', self::Failed => 'Hatalı',
        };
    }
}
