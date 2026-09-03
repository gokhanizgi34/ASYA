<?php

namespace App;

enum SocialSentiment: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::Positive => 'Olumlu',
            self::Neutral => 'Nötr',
            self::Negative => 'Olumsuz',
        };
    }
}
