<?php

namespace App\Http\Controllers;

use App\CampaignContentStatus;
use App\Http\Requests\StoreCampaignContentRequest;
use App\Http\Requests\UpdateCampaignContentRequest;
use App\Models\Campaign;
use App\Models\CampaignContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CampaignContentController extends Controller
{
    public function store(StoreCampaignContentRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->contents()->create([...$request->validated(), 'created_by' => $request->user()->id, 'status' => CampaignContentStatus::Draft]);

        return back()->with('success', 'Kampanya içeriği eklendi.');
    }

    public function update(UpdateCampaignContentRequest $request, Campaign $campaign, CampaignContent $campaignContent): RedirectResponse
    {
        abort_unless($campaignContent->campaign_id === $campaign->id, 404);
        $campaignContent->update([...$request->validated(), 'status' => CampaignContentStatus::Draft, 'approved_at' => null]);

        return back()->with('success', 'Kampanya içeriği güncellendi ve yeniden onaya alındı.');
    }

    public function destroy(Campaign $campaign, CampaignContent $campaignContent): RedirectResponse
    {
        abort_unless($campaignContent->campaign_id === $campaign->id, 404);
        Gate::authorize('update', $campaign);
        abort_if($campaignContent->status === CampaignContentStatus::Published, 422, 'Yayınlanmış içerik silinemez.');
        $campaignContent->delete();

        return back()->with('success', 'Kampanya içeriği silindi.');
    }
}
