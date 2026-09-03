<?php

namespace App\Policies;

use App\Models\ColumnistDraft;
use App\Models\User;

class ColumnistDraftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function view(User $user, ColumnistDraft $draft): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $draft->agency_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ColumnistDraft $draft): bool
    {
        return $this->view($user, $draft);
    }

    public function delete(User $user, ColumnistDraft $draft): bool
    {
        return false;
    }

    public function restore(User $user, ColumnistDraft $draft): bool
    {
        return false;
    }

    public function forceDelete(User $user, ColumnistDraft $draft): bool
    {
        return false;
    }
}
