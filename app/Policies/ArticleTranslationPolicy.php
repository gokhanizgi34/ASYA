<?php

namespace App\Policies;

use App\Models\ArticleTranslation;
use App\Models\User;

class ArticleTranslationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function view(User $user, ArticleTranslation $translation): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $translation->agency_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ArticleTranslation $translation): bool
    {
        return $this->view($user, $translation);
    }

    public function delete(User $user, ArticleTranslation $translation): bool
    {
        return $this->view($user, $translation) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function restore(User $user, ArticleTranslation $translation): bool
    {
        return false;
    }

    public function forceDelete(User $user, ArticleTranslation $translation): bool
    {
        return false;
    }
}
