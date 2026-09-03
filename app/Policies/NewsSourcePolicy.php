<?php

namespace App\Policies;

use App\Models\NewsSource;
use App\Models\User;

class NewsSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function view(User $user, NewsSource $source): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $source->agency_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, NewsSource $source): bool
    {
        return $this->view($user, $source);
    }

    public function delete(User $user, NewsSource $source): bool
    {
        return $this->view($user, $source) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function restore(User $user, NewsSource $source): bool
    {
        return false;
    }

    public function forceDelete(User $user, NewsSource $source): bool
    {
        return false;
    }
}
