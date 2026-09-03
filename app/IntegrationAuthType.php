<?php

namespace App;

enum IntegrationAuthType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Basic = 'basic';
    case ApiKeyHeader = 'api_key_header';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Kimlik doğrulama yok',
            self::Bearer => 'Bearer token',
            self::Basic => 'Kullanıcı adı / parola',
            self::ApiKeyHeader => 'API anahtarı başlığı',
        };
    }

    public function requiresCredential(): bool
    {
        return $this !== self::None;
    }
}
