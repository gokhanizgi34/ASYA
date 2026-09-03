<?php

namespace App;

enum SourceTrustBand: string
{
    case High = 'high';
    case Medium = 'medium';
    case Caution = 'caution';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High => 'Yüksek güven',
            self::Medium => 'Orta güven',
            self::Caution => 'Dikkat',
            self::Low => 'Düşük güven',
        };
    }
}
