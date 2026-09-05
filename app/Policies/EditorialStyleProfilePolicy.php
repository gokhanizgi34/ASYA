<?php

namespace App\Policies;

use App\Models\EditorialStyleProfile;
use App\Models\User;

class EditorialStyleProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EditorialStyleProfile $profile): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $profile->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function update(User $user, EditorialStyleProfile $profile): bool
    {
        return $this->create($user) && $this->view($user, $profile);
    }

    public function delete(User $user, EditorialStyleProfile $profile): bool
    {
        return $this->update($user, $profile);
    }
}
