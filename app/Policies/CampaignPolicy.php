<?php

namespace App\Policies;

use App\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $campaign->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->view($user, $campaign) && $campaign->status === CampaignStatus::Draft;
    }

    public function changeStatus(User $user, Campaign $campaign): bool
    {
        return $this->view($user, $campaign) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->view($user, $campaign) && $campaign->status === CampaignStatus::Draft && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return $this->view($user, $campaign) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return false;
    }
}
