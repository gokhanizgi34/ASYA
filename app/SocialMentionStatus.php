<?php

namespace App;

enum SocialMentionStatus: string
{
    case New = 'new';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Yeni',
            self::Reviewing => 'İnceleniyor',
            self::Resolved => 'Çözüldü',
            self::Ignored => 'Yok sayıldı',
        };
    }
}
