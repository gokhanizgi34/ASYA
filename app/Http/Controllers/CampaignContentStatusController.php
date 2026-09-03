<?php

namespace App\Http\Controllers;

use App\CampaignContentStatus;
use App\Models\Campaign;
use App\Models\CampaignContent;
use App\Services\CampaignWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CampaignContentStatusController extends Controller
{
    public function __invoke(Request $request, Campaign $campaign, CampaignContent $campaignContent, CampaignWorkflow $workflow): RedirectResponse
    {
        abort_unless($campaignContent->campaign_id === $campaign->id, 404);
        Gate::authorize('changeStatus', $campaign);
        $data = $request->validate(['status' => ['required', Rule::enum(CampaignContentStatus::class)]]);
        $workflow->transitionContent($campaignContent, CampaignContentStatus::from($data['status']));

        return back()->with('success', 'İçerik durumu güncellendi.');
    }
}
