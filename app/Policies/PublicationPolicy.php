<?php

namespace App\Policies;

use App\Models\Publication;
use App\Models\User;

class PublicationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Publication $publication): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $publication->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function update(User $user, Publication $publication): bool
    {
        return $this->create($user) && $this->view($user, $publication) && $publication->canBeDispatched();
    }

    public function delete(User $user, Publication $publication): bool
    {
        return false;
    }

    public function restore(User $user, Publication $publication): bool
    {
        return false;
    }

    public function forceDelete(User $user, Publication $publication): bool
    {
        return false;
    }
}
