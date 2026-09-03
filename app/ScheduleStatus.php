<?php

namespace App;

enum ScheduleStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Bekliyor', self::Processing => 'İşleniyor', self::Completed => 'Tamamlandı', self::Failed => 'Hatalı', self::Cancelled => 'İptal Edildi'
        };
    }
}
