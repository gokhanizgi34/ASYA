<?php

namespace App\Policies;

use App\Models\TrendTopic;
use App\Models\User;

class TrendTopicPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TrendTopic $trendTopic): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $trendTopic->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function update(User $user, TrendTopic $trendTopic): bool
    {
        return false;
    }

    public function delete(User $user, TrendTopic $trendTopic): bool
    {
        return false;
    }

    public function restore(User $user, TrendTopic $trendTopic): bool
    {
        return false;
    }

    public function forceDelete(User $user, TrendTopic $trendTopic): bool
    {
        return false;
    }
}
