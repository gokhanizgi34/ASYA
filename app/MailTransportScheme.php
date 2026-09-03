<?php

namespace App;

enum MailTransportScheme: string
{
    case Smtp = 'smtp';
    case Smtps = 'smtps';

    public function label(): string
    {
        return match ($this) {
            self::Smtp => 'STARTTLS / SMTP (genellikle 587)',
            self::Smtps => 'SSL / SMTPS (genellikle 465)',
        };
    }
}
