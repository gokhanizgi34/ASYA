<?php

namespace App\Policies;

use App\Models\SystemNotification;
use App\Models\User;

class SystemNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, SystemNotification $systemNotification): bool
    {
        return $user->id === $systemNotification->recipient_user_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SystemNotification $systemNotification): bool
    {
        return $this->view($user, $systemNotification);
    }

    public function delete(User $user, SystemNotification $systemNotification): bool
    {
        return false;
    }

    public function restore(User $user, SystemNotification $systemNotification): bool
    {
        return false;
    }

    public function forceDelete(User $user, SystemNotification $systemNotification): bool
    {
        return false;
    }
}
