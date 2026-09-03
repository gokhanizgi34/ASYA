<?php

namespace App;

enum BlacklistRuleType: string
{
    case Keyword = 'keyword';
    case Domain = 'domain';
    case UrlPrefix = 'url_prefix';
    case Source = 'source';

    public function label(): string
    {
        return match ($this) {
            self::Keyword => 'Anahtar kelime',
            self::Domain => 'Alan adı',
            self::UrlPrefix => 'URL başlangıcı',
            self::Source => 'Kaynak adı',
        };
    }
}
