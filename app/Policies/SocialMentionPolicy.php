<?php

namespace App\Policies;

use App\Models\SocialMention;
use App\Models\User;

class SocialMentionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function view(User $user, SocialMention $mention): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $mention->agency_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SocialMention $mention): bool
    {
        return $this->view($user, $mention);
    }

    public function delete(User $user, SocialMention $mention): bool
    {
        return false;
    }

    public function restore(User $user, SocialMention $mention): bool
    {
        return false;
    }

    public function forceDelete(User $user, SocialMention $mention): bool
    {
        return false;
    }
}
