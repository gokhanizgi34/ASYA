<?php

namespace App\Policies;

use App\Models\AiPrompt;
use App\Models\User;

class AiPromptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function view(User $user, AiPrompt $aiPrompt): bool
    {
        return $user->isSystemAdministrator()
            || $aiPrompt->agency_id === null
            || $user->agency_id === $aiPrompt->agency_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AiPrompt $aiPrompt): bool
    {
        return $user->isSystemAdministrator()
            || ($user->isAgencyOwner() && $aiPrompt->agency_id !== null && $user->agency_id === $aiPrompt->agency_id);
    }

    public function delete(User $user, AiPrompt $aiPrompt): bool
    {
        return $this->update($user, $aiPrompt);
    }

    public function restore(User $user, AiPrompt $aiPrompt): bool
    {
        return $this->update($user, $aiPrompt);
    }

    public function forceDelete(User $user, AiPrompt $aiPrompt): bool
    {
        return false;
    }
}
