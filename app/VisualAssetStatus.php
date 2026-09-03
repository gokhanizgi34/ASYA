<?php

namespace App;

enum VisualAssetStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case NeedsReplacement = 'needs_replacement';
    case Generating = 'generating';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Değerlendiriliyor',
            self::Approved => 'Kullanıma Hazır',
            self::NeedsReplacement => 'Değiştirilmeli',
            self::Generating => 'Üretim Kuyruğunda',
            self::Failed => 'Hatalı',
        };
    }
}
