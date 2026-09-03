<?php

namespace App;

enum DatabaseBackupStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Missing = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Hazırlanıyor',
            self::Completed => 'Hazır',
            self::Failed => 'Başarısız',
            self::Missing => 'Dosya kayıp',
        };
    }
}
