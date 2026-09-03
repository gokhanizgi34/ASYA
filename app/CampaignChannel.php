<?php

namespace App;

enum CampaignChannel: string
{
    case Website = 'website';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case X = 'x';
    case LinkedIn = 'linkedin';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Web Sitesi', self::Facebook => 'Facebook', self::Instagram => 'Instagram', self::X => 'X', self::LinkedIn => 'LinkedIn', self::Email => 'E-posta'
        };
    }
}
