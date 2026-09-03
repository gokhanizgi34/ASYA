<?php

namespace App\Http\Controllers;

use App\CampaignStatus;
use App\Models\Campaign;
use App\Services\CampaignWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CampaignStatusController extends Controller
{
    public function __invoke(Request $request, Campaign $campaign, CampaignWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('changeStatus', $campaign);
        $data = $request->validate(['status' => ['required', Rule::enum(CampaignStatus::class)]]);
        $workflow->transitionCampaign($campaign, CampaignStatus::from($data['status']));

        return back()->with('success', 'Kampanya durumu güncellendi.');
    }
}
