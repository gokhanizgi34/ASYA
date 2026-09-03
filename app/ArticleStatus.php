<?php

namespace App;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::PendingApproval => 'Onay Bekliyor',
            self::Published => 'Yayında',
            self::Failed => 'Hatalı',
        };
    }
}
