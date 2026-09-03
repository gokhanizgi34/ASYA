<?php

namespace App\Policies;

use App\Models\AiColumnist;
use App\Models\User;

class AiColumnistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function view(User $user, AiColumnist $columnist): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $columnist->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function update(User $user, AiColumnist $columnist): bool
    {
        return $this->view($user, $columnist) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function delete(User $user, AiColumnist $columnist): bool
    {
        return $this->update($user, $columnist);
    }

    public function restore(User $user, AiColumnist $columnist): bool
    {
        return false;
    }

    public function forceDelete(User $user, AiColumnist $columnist): bool
    {
        return false;
    }
}
