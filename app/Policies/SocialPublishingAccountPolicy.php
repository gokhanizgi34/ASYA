<?php

namespace App\Policies;

use App\Models\SocialPublishingAccount;
use App\Models\User;

class SocialPublishingAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function view(User $user, SocialPublishingAccount $account): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $account->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function update(User $user, SocialPublishingAccount $account): bool
    {
        return $this->view($user, $account) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function delete(User $user, SocialPublishingAccount $account): bool
    {
        return $this->update($user, $account);
    }

    public function restore(User $user, SocialPublishingAccount $account): bool
    {
        return false;
    }

    public function forceDelete(User $user, SocialPublishingAccount $account): bool
    {
        return false;
    }
}
