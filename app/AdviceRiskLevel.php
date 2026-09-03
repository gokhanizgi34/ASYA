<?php

namespace App;

enum AdviceRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Düşük',
            self::Medium => 'Orta',
            self::High => 'Yüksek',
            self::Critical => 'Kritik',
        };
    }
}
