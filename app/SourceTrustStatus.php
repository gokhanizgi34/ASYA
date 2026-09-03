<?php

namespace App;

enum SourceTrustStatus: string
{
    case Unverified = 'unverified';
    case ManualReview = 'manual_review';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Doğrulanmadı',
            self::ManualReview => 'Manuel Onay',
            self::Verified => 'Doğrulandı',
        };
    }
}
