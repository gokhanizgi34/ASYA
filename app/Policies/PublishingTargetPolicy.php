<?php

namespace App\Policies;

use App\Models\PublishingTarget;
use App\Models\User;

class PublishingTargetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function view(User $user, PublishingTarget $publishingTarget): bool
    {
        return $user->isSystemAdministrator() || ($user->isAgencyOwner() && $user->agency_id === $publishingTarget->agency_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PublishingTarget $publishingTarget): bool
    {
        return $this->view($user, $publishingTarget);
    }

    public function delete(User $user, PublishingTarget $publishingTarget): bool
    {
        return $this->view($user, $publishingTarget);
    }

    public function restore(User $user, PublishingTarget $publishingTarget): bool
    {
        return $this->view($user, $publishingTarget);
    }

    public function forceDelete(User $user, PublishingTarget $publishingTarget): bool
    {
        return false;
    }
}
