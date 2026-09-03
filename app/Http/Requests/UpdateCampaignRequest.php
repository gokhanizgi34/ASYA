<?php

namespace App\Http\Requests;

use App\Models\Campaign;

class UpdateCampaignRequest extends StoreCampaignRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        return $campaign instanceof Campaign && ($this->user()?->can('update', $campaign) ?? false);
    }

    protected function campaignForUniqueRule(): ?Campaign
    {
        $campaign = $this->route('campaign');

        return $campaign instanceof Campaign ? $campaign : null;
    }
}
