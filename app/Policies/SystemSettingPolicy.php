<?php

namespace App\Policies;

use App\Models\SystemSetting;
use App\Models\User;

class SystemSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function view(User $user, SystemSetting $systemSetting): bool
    {
        return $this->viewAny($user)
            && ($user->isSystemAdministrator() || $user->agency_id === $systemSetting->agency_id);
    }

    public function updateAny(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SystemSetting $systemSetting): bool
    {
        return $this->view($user, $systemSetting);
    }

    public function delete(User $user, SystemSetting $systemSetting): bool
    {
        return false;
    }

    public function restore(User $user, SystemSetting $systemSetting): bool
    {
        return false;
    }

    public function forceDelete(User $user, SystemSetting $systemSetting): bool
    {
        return false;
    }
}
