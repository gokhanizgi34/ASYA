<?php

namespace App\Http\Controllers;

use App\CampaignContentStatus;
use App\Models\Campaign;
use App\Models\CampaignContent;
use App\Services\AutomaticArticleVisualManager;
use App\Services\CampaignWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CampaignContentStatusController extends Controller
{
    public function __invoke(
        Request $request,
        Campaign $campaign,
        CampaignContent $campaignContent,
        CampaignWorkflow $workflow,
        AutomaticArticleVisualManager $visualManager,
    ): RedirectResponse {
        abort_unless($campaignContent->campaign_id === $campaign->id, 404);
        Gate::authorize('changeStatus', $campaign);
        $data = $request->validate(['status' => ['required', Rule::enum(CampaignContentStatus::class)]]);
        $targetStatus = CampaignContentStatus::from($data['status']);
        $workflow->transitionContent($campaignContent, $targetStatus);

        if (in_array($targetStatus, [CampaignContentStatus::Approved, CampaignContentStatus::Published], true)) {
            $campaignContent->loadMissing('article');

            if ($campaignContent->article) {
                $visualManager->ensure($campaignContent->article);
            }
        }

        return back()->with('success', 'İçerik durumu güncellendi.');
    }
}
