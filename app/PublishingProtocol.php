<?php

namespace App;

enum PublishingProtocol: string
{
    case WordPressRest = 'wordpress_rest';
    case WordPressXmlRpc = 'wordpress_xmlrpc';

    public function label(): string
    {
        return match ($this) {
            self::WordPressRest => 'WordPress REST API',
            self::WordPressXmlRpc => 'WordPress XML-RPC',
        };
    }
}
