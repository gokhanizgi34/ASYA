<?php

namespace App;

enum AdviceLetterStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Answered = 'answered';
    case Published = 'published';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Bekliyor',
            self::InReview => 'İnceleniyor',
            self::Answered => 'Yanıtlandı',
            self::Published => 'Yayımlandı',
            self::Rejected => 'Reddedildi',
        };
    }
}
