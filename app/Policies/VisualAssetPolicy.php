<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisualAsset;

class VisualAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VisualAsset $visualAsset): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $visualAsset->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function update(User $user, VisualAsset $visualAsset): bool
    {
        return $this->view($user, $visualAsset);
    }

    public function delete(User $user, VisualAsset $visualAsset): bool
    {
        if (! $this->view($user, $visualAsset) || $visualAsset->is_selected) {
            return false;
        }

        return $user->isSystemAdministrator()
            || $user->isAgencyOwner()
            || $visualAsset->uploaded_by === $user->id;
    }

    public function restore(User $user, VisualAsset $visualAsset): bool
    {
        return $this->view($user, $visualAsset) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function forceDelete(User $user, VisualAsset $visualAsset): bool
    {
        return false;
    }
}
