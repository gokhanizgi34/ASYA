<?php

namespace App\Policies;

use App\Models\ContentBatch;
use App\Models\User;

class ContentBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContentBatch $contentBatch): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id === $contentBatch->agency_id;
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdministrator() || $user->agency_id !== null;
    }

    public function update(User $user, ContentBatch $contentBatch): bool
    {
        return $this->view($user, $contentBatch) && $contentBatch->canBeDispatched();
    }

    public function delete(User $user, ContentBatch $contentBatch): bool
    {
        return false;
    }

    public function restore(User $user, ContentBatch $contentBatch): bool
    {
        return false;
    }

    public function forceDelete(User $user, ContentBatch $contentBatch): bool
    {
        return false;
    }
}
