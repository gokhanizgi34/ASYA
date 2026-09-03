<?php

namespace App\Policies;

use App\Models\LearnedRoute;
use App\Models\User;

class LearnedRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function view(User $user, LearnedRoute $learnedRoute): bool
    {
        return $this->viewAny($user)
            && ($user->isSystemAdministrator() || $user->agency_id === $learnedRoute->agency_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LearnedRoute $learnedRoute): bool
    {
        return $this->view($user, $learnedRoute);
    }

    public function delete(User $user, LearnedRoute $learnedRoute): bool
    {
        return false;
    }

    public function restore(User $user, LearnedRoute $learnedRoute): bool
    {
        return false;
    }

    public function forceDelete(User $user, LearnedRoute $learnedRoute): bool
    {
        return false;
    }
}
