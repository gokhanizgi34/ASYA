<?php

namespace App\Policies;

use App\Models\SocialPost;
use App\Models\User;

class SocialPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function view(User $user, SocialPost $post): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $post->agency_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SocialPost $post): bool
    {
        return $this->view($user, $post);
    }

    public function delete(User $user, SocialPost $post): bool
    {
        return false;
    }

    public function restore(User $user, SocialPost $post): bool
    {
        return false;
    }

    public function forceDelete(User $user, SocialPost $post): bool
    {
        return false;
    }
}
