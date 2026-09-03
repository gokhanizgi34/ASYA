<?php

namespace App;

enum ErrorLogStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Açık',
            self::Resolved => 'Çözüldü',
            self::Ignored => 'Yok sayıldı',
        };
    }
}
