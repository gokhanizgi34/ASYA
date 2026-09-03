<?php

namespace App;

enum ErrorSeverity: string
{
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Warning => 'Uyarı',
            self::Error => 'Hata',
            self::Critical => 'Kritik',
        };
    }
}
