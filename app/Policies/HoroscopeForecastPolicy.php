<?php

namespace App\Policies;

use App\Models\HoroscopeForecast;
use App\Models\User;

class HoroscopeForecastPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function view(User $user, HoroscopeForecast $forecast): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $forecast->agency_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, HoroscopeForecast $forecast): bool
    {
        return $this->view($user, $forecast);
    }

    public function delete(User $user, HoroscopeForecast $forecast): bool
    {
        return $this->view($user, $forecast) && ($user->isSystemAdministrator() || $user->isAgencyOwner());
    }

    public function restore(User $user, HoroscopeForecast $forecast): bool
    {
        return false;
    }

    public function forceDelete(User $user, HoroscopeForecast $forecast): bool
    {
        return false;
    }
}
