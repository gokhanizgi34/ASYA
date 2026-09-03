<?php

namespace App\Policies;

use App\Models\ApiIntegration;
use App\Models\User;

class ApiIntegrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function view(User $user, ApiIntegration $apiIntegration): bool
    {
        return $this->viewAny($user)
            && ($user->isSystemAdministrator() || $user->agency_id === $apiIntegration->agency_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ApiIntegration $apiIntegration): bool
    {
        return $this->view($user, $apiIntegration);
    }

    public function delete(User $user, ApiIntegration $apiIntegration): bool
    {
        return $this->view($user, $apiIntegration);
    }

    public function restore(User $user, ApiIntegration $apiIntegration): bool
    {
        return false;
    }

    public function forceDelete(User $user, ApiIntegration $apiIntegration): bool
    {
        return false;
    }
}
