<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $user->isSystemAdministrator()
            || ($user->isAgencyOwner() && $user->agency_id === $ticket->agency_id)
            || $user->id === $ticket->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return $user->isSystemAdministrator();
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return false;
    }

    public function restore(User $user, SupportTicket $ticket): bool
    {
        return false;
    }

    public function forceDelete(User $user, SupportTicket $ticket): bool
    {
        return false;
    }
}
