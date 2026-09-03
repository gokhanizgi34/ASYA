<?php

namespace App\Services;

use App\CampaignContentStatus;
use App\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignContent;
use Illuminate\Validation\ValidationException;

class CampaignWorkflow
{
    /** @return array<int, CampaignStatus> */
    public function availableCampaignTransitions(Campaign $campaign): array
    {
        return match ($campaign->status) {
            CampaignStatus::Draft => [CampaignStatus::Scheduled, CampaignStatus::Cancelled],
            CampaignStatus::Scheduled => [CampaignStatus::Active, CampaignStatus::Cancelled],
            CampaignStatus::Active => [CampaignStatus::Paused, CampaignStatus::Completed, CampaignStatus::Cancelled],
            CampaignStatus::Paused => [CampaignStatus::Active, CampaignStatus::Completed, CampaignStatus::Cancelled],
            CampaignStatus::Completed, CampaignStatus::Cancelled => [],
        };
    }

    /** @return array<int, CampaignContentStatus> */
    public function availableContentTransitions(CampaignContent $content): array
    {
        return match ($content->status) {
            CampaignContentStatus::Draft => [CampaignContentStatus::Approved],
            CampaignContentStatus::Approved => [CampaignContentStatus::Draft, CampaignContentStatus::Published],
            CampaignContentStatus::Published => [],
        };
    }

    public function transitionCampaign(Campaign $campaign, CampaignStatus $target): void
    {
        if (! in_array($target, $this->availableCampaignTransitions($campaign), true)) {
            throw ValidationException::withMessages(['status' => 'Bu kampanya durum geçişine izin verilmiyor.']);
        }
        if ($target === CampaignStatus::Scheduled) {
            if (! $campaign->starts_at || ! $campaign->ends_at || $campaign->ends_at->lte($campaign->starts_at)) {
                throw ValidationException::withMessages(['status' => 'Planlama için geçerli başlangıç ve bitiş zamanı zorunludur.']);
            }
            $approvedChannels = $campaign->contents()->where('status', CampaignContentStatus::Approved)->get(['channel'])->map(fn (CampaignContent $content): string => $content->channel->value)->unique()->all();
            if (array_diff($campaign->channels, $approvedChannels) !== []) {
                throw ValidationException::withMessages(['status' => 'Her kampanya kanalı için en az bir onaylı içerik zorunludur.']);
            }
        }
        $campaign->update(['status' => $target]);
    }

    public function transitionContent(CampaignContent $content, CampaignContentStatus $target): void
    {
        if (! in_array($target, $this->availableContentTransitions($content), true)) {
            throw ValidationException::withMessages(['status' => 'Bu içerik durum geçişine izin verilmiyor.']);
        }
        $content->loadMissing('campaign');
        if (in_array($target, [CampaignContentStatus::Draft, CampaignContentStatus::Approved], true) && $content->campaign->status !== CampaignStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'İçerik onayı yalnızca taslak kampanyada değiştirilebilir.']);
        }
        if ($target === CampaignContentStatus::Published && $content->campaign->status !== CampaignStatus::Active) {
            throw ValidationException::withMessages(['status' => 'İçerik yalnızca aktif kampanyada yayınlandı olarak işaretlenebilir.']);
        }
        $content->update([
            'status' => $target,
            'approved_at' => $target === CampaignContentStatus::Approved ? now() : ($target === CampaignContentStatus::Draft ? null : $content->approved_at),
            'published_at' => $target === CampaignContentStatus::Published ? now() : null,
        ]);
    }
}
