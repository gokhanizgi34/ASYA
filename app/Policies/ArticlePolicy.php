<?php

namespace App\Policies;

use App\ArticleStatus;
use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Article $article): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $article->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function update(User $user, Article $article): bool
    {
        return $this->view($user, $article);
    }

    public function delete(User $user, Article $article): bool
    {
        if (! $this->view($user, $article)) {
            return false;
        }

        return $user->isSystemAdministrator()
            || $user->isAgencyOwner()
            || $article->status !== ArticleStatus::Published;
    }

    public function restore(User $user, Article $article): bool
    {
        return $this->view($user, $article) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function forceDelete(User $user, Article $article): bool
    {
        return false;
    }
}
