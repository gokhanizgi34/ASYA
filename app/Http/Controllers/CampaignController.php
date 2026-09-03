<?php

namespace App\Http\Controllers;

use App\CampaignChannel;
use App\CampaignContentStatus;
use App\CampaignStatus;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\User;
use App\Services\CampaignWorkflow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Campaign::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $status = (string) $request->query('status', '');
        $query = Campaign::query()->visibleTo($user)->with(['agency', 'owner'])->withCount('contents');
        if (CampaignStatus::tryFrom($status)) {
            $query->where('status', $status);
        }
        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->query('q')).'%');
        }

        return view('campaigns.index', ['campaigns' => $query->orderByDesc('created_at')->paginate(15)->withQueryString(), 'statuses' => CampaignStatus::cases()]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Campaign::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('campaigns.create', $this->formOptions($user));
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = DB::transaction(function () use ($request): Campaign {
            $campaign = Campaign::query()->create([...$request->campaignAttributes(), 'owner_id' => $request->user()->id]);
            foreach ($request->validated('contents', []) as $content) {
                $campaign->contents()->create([...$content, 'created_by' => $request->user()->id, 'status' => CampaignContentStatus::Draft]);
            }

            return $campaign;
        }, 3);

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Kampanya taslağı oluşturuldu.');
    }

    public function show(Campaign $campaign, CampaignWorkflow $workflow): View
    {
        Gate::authorize('view', $campaign);

        return view('campaigns.show', ['campaign' => $campaign->load(['agency', 'owner', 'contents.article', 'contents.creator']), 'channels' => CampaignChannel::cases(), 'articles' => Article::query()->where('agency_id', $campaign->agency_id)->orderByDesc('created_at')->limit(200)->get(), 'campaignTransitions' => $workflow->availableCampaignTransitions($campaign), 'contentTransitions' => $campaign->contents->mapWithKeys(fn ($content): array => [$content->id => $workflow->availableContentTransitions($content)])]);
    }

    public function edit(Request $request, Campaign $campaign): View
    {
        Gate::authorize('update', $campaign);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('campaigns.edit', ['campaign' => $campaign, ...$this->formOptions($user, $campaign)]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($request->campaignAttributes());

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Kampanya güncellendi.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('delete', $campaign);
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Kampanya geri alınabilir şekilde silindi.');
    }

    /** @return array{agencies: Collection<int, Agency>, channels: array<int, CampaignChannel>} */
    private function formOptions(User $user, ?Campaign $campaign = null): array
    {
        return ['agencies' => Agency::query()->where(fn ($query) => $query->where('is_active', true)->when($campaign, fn ($nested) => $nested->orWhereKey($campaign->agency_id)))->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get(), 'channels' => CampaignChannel::cases()];
    }
}
