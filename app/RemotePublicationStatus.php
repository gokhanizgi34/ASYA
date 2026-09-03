<?php

namespace App;

enum RemotePublicationStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Publish = 'publish';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Uzak Taslak',
            self::Pending => 'Uzak Onay Bekliyor',
            self::Publish => 'Doğrudan Yayımla',
            self::Private => 'Özel Yayın',
        };
    }
}
