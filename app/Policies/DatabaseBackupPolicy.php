<?php

namespace App\Policies;

use App\Models\DatabaseBackup;
use App\Models\User;

class DatabaseBackupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator();
    }

    public function view(User $user, DatabaseBackup $databaseBackup): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DatabaseBackup $databaseBackup): bool
    {
        return false;
    }

    public function delete(User $user, DatabaseBackup $databaseBackup): bool
    {
        return $this->view($user, $databaseBackup);
    }

    public function restore(User $user, DatabaseBackup $databaseBackup): bool
    {
        return false;
    }

    public function forceDelete(User $user, DatabaseBackup $databaseBackup): bool
    {
        return false;
    }
}
