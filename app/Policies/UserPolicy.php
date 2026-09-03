<?php

namespace App\Policies;

use App\Models\User;
use App\UserRole;

class UserPolicy
{
    public function viewAny(User $currentUser): bool
    {
        return $currentUser->isSystemAdministrator() || $currentUser->isAgencyOwner();
    }

    public function view(User $currentUser, User $user): bool
    {
        return $currentUser->isSystemAdministrator()
            || $currentUser->is($user)
            || ($currentUser->isAgencyOwner() && $currentUser->agency_id === $user->agency_id);
    }

    public function create(User $currentUser): bool
    {
        return $currentUser->isSystemAdministrator() || $currentUser->isAgencyOwner();
    }

    public function update(User $currentUser, User $user): bool
    {
        return $currentUser->isSystemAdministrator()
            || ($currentUser->isAgencyOwner()
                && $currentUser->agency_id === $user->agency_id
                && $user->role === UserRole::Editor);
    }

    public function updateStatus(User $currentUser, User $user): bool
    {
        return $this->update($currentUser, $user);
    }

    public function delete(User $currentUser, User $user): bool
    {
        return false;
    }

    public function restore(User $currentUser, User $user): bool
    {
        return false;
    }

    public function forceDelete(User $currentUser, User $user): bool
    {
        return false;
    }
}
