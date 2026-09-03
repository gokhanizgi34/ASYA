<?php

namespace App;

enum VisualSourceType: string
{
    case Original = 'original';
    case Archive = 'archive';
    case AiGenerated = 'ai_generated';
    case Upload = 'upload';

    public function label(): string
    {
        return match ($this) {
            self::Original => 'Kaynak Görseli',
            self::Archive => 'Medya Arşivi',
            self::AiGenerated => 'AI Üretimi',
            self::Upload => 'Manuel Yükleme',
        };
    }
}
