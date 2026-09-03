<?php

namespace App;

enum ColumnistDraftStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak', self::Review => 'İncelemede',
            self::Approved => 'Onaylandı', self::Rejected => 'Reddedildi',
        };
    }
}
