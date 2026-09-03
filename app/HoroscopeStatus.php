<?php

namespace App;

enum HoroscopeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return $this === self::Draft ? 'Taslak' : 'Yayımlandı';
    }
}
