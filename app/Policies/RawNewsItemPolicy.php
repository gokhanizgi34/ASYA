<?php

namespace App\Policies;

use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;

class RawNewsItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RawNewsItem $rawNewsItem): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $rawNewsItem->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function update(User $user, RawNewsItem $rawNewsItem): bool
    {
        return $this->view($user, $rawNewsItem);
    }

    public function delete(User $user, RawNewsItem $rawNewsItem): bool
    {
        if (! $this->view($user, $rawNewsItem)) {
            return false;
        }

        return $user->isSystemAdministrator()
            || $user->isAgencyOwner()
            || in_array($rawNewsItem->status, [RawNewsStatus::Pending, RawNewsStatus::Rejected], true);
    }

    public function restore(User $user, RawNewsItem $rawNewsItem): bool
    {
        return $this->view($user, $rawNewsItem) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function forceDelete(User $user, RawNewsItem $rawNewsItem): bool
    {
        return false;
    }
}
