<?php

namespace App;

enum SocialFeedImportStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Çalışıyor',
            self::Completed => 'Tamamlandı',
            self::Partial => 'Kısmi',
            self::Failed => 'Başarısız',
        };
    }
}
