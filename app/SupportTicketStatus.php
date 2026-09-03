<?php

namespace App;

enum SupportTicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Açık',
            self::InProgress => 'İnceleniyor',
            self::Resolved => 'Çözüldü',
            self::Closed => 'Kapatıldı',
        };
    }
}
