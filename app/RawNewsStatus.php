<?php

namespace App;

enum RawNewsStatus: string
{
    case Pending = 'pending';
    case Review = 'review';
    case Queued = 'queued';
    case Processing = 'processing';
    case Processed = 'processed';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Bekliyor',
            self::Review => 'İnceleme',
            self::Queued => 'Kuyrukta',
            self::Processing => 'İşleniyor',
            self::Processed => 'İşlendi',
            self::Rejected => 'Reddedildi',
            self::Failed => 'Hatalı',
        };
    }
}
