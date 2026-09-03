<?php

namespace App\Policies;

use App\Models\AnalyticsSnapshot;
use App\Models\User;

class AnalyticsSnapshotPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AnalyticsSnapshot $snapshot): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $snapshot->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function update(User $user, AnalyticsSnapshot $snapshot): bool
    {
        return false;
    }

    public function delete(User $user, AnalyticsSnapshot $snapshot): bool
    {
        return false;
    }

    public function restore(User $user, AnalyticsSnapshot $snapshot): bool
    {
        return false;
    }

    public function forceDelete(User $user, AnalyticsSnapshot $snapshot): bool
    {
        return false;
    }
}
