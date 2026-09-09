<?php

namespace App\Policies;

use App\Models\PublishingTarget;
use App\Models\User;

class PublishingTargetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner() || $user->isEditor();
    }

    public function view(User $user, PublishingTarget $publishingTarget): bool
    {
        return $user->isSystemAdministrator()
            || (($user->isAgencyOwner() || $user->isEditor()) && $user->agency_id === $publishingTarget->agency_id);
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || ($user->isEditor() && $user->agency_id !== null);
    }

    public function update(User $user, PublishingTarget $publishingTarget): bool
    {
        return $this->view($user, $publishingTarget) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function delete(User $user, PublishingTarget $publishingTarget): bool
    {
        return $this->update($user, $publishingTarget);
    }

    public function restore(User $user, PublishingTarget $publishingTarget): bool
    {
        return $this->update($user, $publishingTarget);
    }

    public function forceDelete(User $user, PublishingTarget $publishingTarget): bool
    {
        return false;
    }
}
