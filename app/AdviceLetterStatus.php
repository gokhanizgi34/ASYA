<?php

namespace App;

enum AdviceLetterStatus: string
{
    case Pending = 'pending';
    case Answered = 'answered';
    case Published = 'published';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Bekliyor',
            self::Answered => 'Yanıtlandı',
            self::Published => 'Yayınlandı',
            self::Rejected => 'Reddedildi',
        };
    }
}
