<?php

namespace App\Policies;

use App\Models\ScheduleEntry;
use App\Models\User;
use App\ScheduleStatus;

class ScheduleEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ScheduleEntry $entry): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $entry->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function update(User $user, ScheduleEntry $entry): bool
    {
        return $this->view($user, $entry) && ($user->isSystemAdministrator() || $user->isAgencyOwner() || $entry->created_by === $user->id) && in_array($entry->status, [ScheduleStatus::Pending, ScheduleStatus::Failed], true);
    }

    public function delete(User $user, ScheduleEntry $entry): bool
    {
        return false;
    }

    public function restore(User $user, ScheduleEntry $entry): bool
    {
        return false;
    }

    public function forceDelete(User $user, ScheduleEntry $entry): bool
    {
        return false;
    }
}
