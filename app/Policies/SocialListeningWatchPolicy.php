<?php

namespace App\Policies;

use App\Models\SocialListeningWatch;
use App\Models\User;

class SocialListeningWatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function view(User $user, SocialListeningWatch $watch): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $watch->agency_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SocialListeningWatch $watch): bool
    {
        return $this->view($user, $watch);
    }

    public function delete(User $user, SocialListeningWatch $watch): bool
    {
        return $this->view($user, $watch) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function restore(User $user, SocialListeningWatch $watch): bool
    {
        return false;
    }

    public function forceDelete(User $user, SocialListeningWatch $watch): bool
    {
        return false;
    }
}
