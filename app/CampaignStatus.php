<?php

namespace App;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak', self::Scheduled => 'Planlandı', self::Active => 'Aktif', self::Paused => 'Duraklatıldı', self::Completed => 'Tamamlandı', self::Cancelled => 'İptal Edildi'
        };
    }
}
