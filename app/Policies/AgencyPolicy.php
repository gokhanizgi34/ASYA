<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\User;
use App\UserRole;

class AgencyPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::SystemAdministrator, UserRole::AgencyOwner], true);
    }

    public function view(User $user, Agency $agency): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $agency->id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator();
    }

    public function update(User $user, Agency $agency): bool
    {
        return $user->isSystemAdministrator() || ($user->role === UserRole::AgencyOwner && $user->agency_id === $agency->id);
    }

    public function updateStatus(User $user, Agency $agency): bool
    {
        return $user->isSystemAdministrator();
    }

    public function delete(User $user, Agency $agency): bool
    {
        return false;
    }

    public function restore(User $user, Agency $agency): bool
    {
        return false;
    }

    public function forceDelete(User $user, Agency $agency): bool
    {
        return false;
    }
}
