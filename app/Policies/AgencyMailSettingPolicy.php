<?php

namespace App\Policies;

use App\Models\AgencyMailSetting;
use App\Models\User;

class AgencyMailSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function view(User $user, AgencyMailSetting $setting): bool
    {
        return $user->isSystemAdministrator()
            || ($user->isAgencyOwner() && $user->agency_id === $setting->agency_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AgencyMailSetting $setting): bool
    {
        return $this->view($user, $setting);
    }

    public function delete(User $user, AgencyMailSetting $setting): bool
    {
        return $this->view($user, $setting);
    }

    public function restore(User $user, AgencyMailSetting $setting): bool
    {
        return false;
    }

    public function forceDelete(User $user, AgencyMailSetting $setting): bool
    {
        return false;
    }
}
