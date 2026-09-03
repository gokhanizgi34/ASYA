<?php

namespace App\Policies;

use App\Models\BlacklistRule;
use App\Models\User;

class BlacklistRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function view(User $user, BlacklistRule $blacklistRule): bool
    {
        return $this->viewAny($user) && ($user->isSystemAdministrator() || $user->agency_id === $blacklistRule->agency_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, BlacklistRule $blacklistRule): bool
    {
        return $this->view($user, $blacklistRule);
    }

    public function delete(User $user, BlacklistRule $blacklistRule): bool
    {
        return $this->view($user, $blacklistRule);
    }

    public function restore(User $user, BlacklistRule $blacklistRule): bool
    {
        return false;
    }

    public function forceDelete(User $user, BlacklistRule $blacklistRule): bool
    {
        return false;
    }
}
