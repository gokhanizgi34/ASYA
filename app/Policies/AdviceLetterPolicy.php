<?php

namespace App\Policies;

use App\Models\AdviceLetter;
use App\Models\User;

class AdviceLetterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function view(User $user, AdviceLetter $adviceLetter): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $adviceLetter->agency_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AdviceLetter $adviceLetter): bool
    {
        return $this->view($user, $adviceLetter) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function delete(User $user, AdviceLetter $adviceLetter): bool
    {
        return $this->update($user, $adviceLetter);
    }

    public function restore(User $user, AdviceLetter $adviceLetter): bool
    {
        return false;
    }

    public function forceDelete(User $user, AdviceLetter $adviceLetter): bool
    {
        return false;
    }
}
