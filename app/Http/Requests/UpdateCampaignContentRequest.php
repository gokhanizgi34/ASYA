<?php

namespace App\Http\Requests;

use App\CampaignContentStatus;
use App\Models\Campaign;
use App\Models\CampaignContent;

class UpdateCampaignContentRequest extends StoreCampaignContentRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');
        $content = $this->route('campaignContent');

        return $campaign instanceof Campaign && $content instanceof CampaignContent && $content->campaign_id === $campaign->id && $content->status !== CampaignContentStatus::Published && ($this->user()?->can('update', $campaign) ?? false);
    }
}
