<?php

namespace App;

enum SocialPostStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Queued => 'Kuyrukta',
            self::Published => 'Yayımlandı',
            self::Failed => 'Başarısız',
        };
    }
}
