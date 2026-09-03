<?php

namespace App\Policies;

use App\Models\TaxonomyMapping;
use App\Models\User;

class TaxonomyMappingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->isAgencyOwner();
    }

    public function view(User $user, TaxonomyMapping $taxonomyMapping): bool
    {
        return $this->viewAny($user) && ($user->isSystemAdministrator() || $user->agency_id === $taxonomyMapping->agency_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, TaxonomyMapping $taxonomyMapping): bool
    {
        return $this->view($user, $taxonomyMapping);
    }

    public function delete(User $user, TaxonomyMapping $taxonomyMapping): bool
    {
        return $this->view($user, $taxonomyMapping);
    }

    public function restore(User $user, TaxonomyMapping $taxonomyMapping): bool
    {
        return false;
    }

    public function forceDelete(User $user, TaxonomyMapping $taxonomyMapping): bool
    {
        return false;
    }
}
