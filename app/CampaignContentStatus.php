<?php

namespace App;

enum CampaignContentStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak', self::Approved => 'Onaylandı', self::Published => 'Yayınlandı'
        };
    }
}
