<?php

namespace App;

enum CopyrightStatus: string
{
    case Unknown = 'unknown';
    case Licensed = 'licensed';
    case PublicDomain = 'public_domain';
    case Original = 'original';
    case Restricted = 'restricted';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Belirsiz',
            self::Licensed => 'Lisanslı',
            self::PublicDomain => 'Kamu Malı',
            self::Original => 'Özgün',
            self::Restricted => 'Kısıtlı / Telifli',
        };
    }

    public function isSafeForPublishing(): bool
    {
        return in_array($this, [self::Licensed, self::PublicDomain, self::Original], true);
    }
}
