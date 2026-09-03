<?php

namespace App;

enum PromptTone: string
{
    case Neutral = 'neutral';
    case Formal = 'formal';
    case BreakingNews = 'breaking_news';
    case Conversational = 'conversational';
    case Analytical = 'analytical';

    public function label(): string
    {
        return match ($this) {
            self::Neutral => 'Tarafsız',
            self::Formal => 'Resmî',
            self::BreakingNews => 'Son Dakika',
            self::Conversational => 'Sohbet Dili',
            self::Analytical => 'Analitik',
        };
    }
}
