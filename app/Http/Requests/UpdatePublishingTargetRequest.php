<?php

namespace App\Http\Requests;

use App\Models\PublishingTarget;

class UpdatePublishingTargetRequest extends StorePublishingTargetRequest
{
    public function authorize(): bool
    {
        $target = $this->route('publishingTarget');

        return $target instanceof PublishingTarget && ($this->user()?->can('update', $target) ?? false);
    }

    protected function targetForUniqueRule(): ?PublishingTarget
    {
        $target = $this->route('publishingTarget');

        return $target instanceof PublishingTarget ? $target : null;
    }
}
