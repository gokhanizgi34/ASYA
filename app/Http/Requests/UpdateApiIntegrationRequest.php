<?php

namespace App\Http\Requests;

use App\Models\ApiIntegration;

class UpdateApiIntegrationRequest extends StoreApiIntegrationRequest
{
    public function authorize(): bool
    {
        $integration = $this->route('apiIntegration');

        return $integration instanceof ApiIntegration && ($this->user()?->can('update', $integration) ?? false);
    }

    protected function integrationForUniqueRule(): ?ApiIntegration
    {
        $integration = $this->route('apiIntegration');

        return $integration instanceof ApiIntegration ? $integration : null;
    }
}
