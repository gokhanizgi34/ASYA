<?php

namespace App;

enum PublicationStatus: string
{
    case Queued = 'queued';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Kuyrukta',
            self::Publishing => 'Yayımlanıyor',
            self::Published => 'Yayımlandı',
            self::Failed => 'Hatalı',
        };
    }
}
