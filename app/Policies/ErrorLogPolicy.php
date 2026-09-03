<?php

namespace App\Policies;

use App\Models\ErrorLog;
use App\Models\User;

class ErrorLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function view(User $user, ErrorLog $errorLog): bool
    {
        return $this->viewAny($user)
            && ($user->isSystemAdministrator() || $user->agency_id === $errorLog->agency_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ErrorLog $errorLog): bool
    {
        return $this->view($user, $errorLog);
    }

    public function delete(User $user, ErrorLog $errorLog): bool
    {
        return false;
    }

    public function restore(User $user, ErrorLog $errorLog): bool
    {
        return false;
    }

    public function forceDelete(User $user, ErrorLog $errorLog): bool
    {
        return false;
    }
}
