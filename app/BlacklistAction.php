<?php

namespace App;

enum BlacklistAction: string
{
    case Block = 'block';
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Block => 'Engelle',
            self::Review => 'İncelemeye gönder',
        };
    }
}
