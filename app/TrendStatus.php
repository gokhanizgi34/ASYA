<?php

namespace App;

enum TrendStatus: string
{
    case Hot = 'hot';
    case Rising = 'rising';
    case Stable = 'stable';
    case Cooling = 'cooling';

    public function label(): string
    {
        return match ($this) {
            self::Hot => 'Sıcak',
            self::Rising => 'Yükseliyor',
            self::Stable => 'Dengeli',
            self::Cooling => 'Soğuyor',
        };
    }
}
