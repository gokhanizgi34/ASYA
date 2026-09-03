<?php

namespace App;

enum UserRole: string
{
    case SystemAdministrator = 'system_administrator';
    case AgencyOwner = 'agency_owner';
    case Editor = 'editor';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'Sistem Yöneticisi',
            self::AgencyOwner => 'Ajans Sahibi',
            self::Editor => 'Editör',
        };
    }
}
