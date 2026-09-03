<?php

namespace App\Http\Controllers;

use App\CampaignStatus;
use App\Http\Requests\StoreScheduleEntryRequest;
use App\Models\Agency;
use App\Models\Campaign;
use App\Models\EditorialCalendarEvent;
use App\Models\Publication;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\PublicationStatus;
use App\ScheduleAction;
use App\ScheduleStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScheduleEntryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ScheduleEntry::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $query = ScheduleEntry::query()->visibleTo($user)->with(['agency', 'creator', 'publication.article', 'campaign']);
        if (ScheduleStatus::tryFrom((string) $request->query('status'))) {
            $query->where('status', $request->query('status'));
        }
        if (ScheduleAction::tryFrom((string) $request->query('action'))) {
            $query->where('action', $request->query('action'));
        }
        if ($request->filled('date')) {
            $query->whereDate('scheduled_for', $request->query('date'));
        }

        return view('schedules.index', [
            'entries' => $query->orderBy('scheduled_for')->paginate(30)->withQueryString(),
            'statuses' => ScheduleStatus::cases(),
            'actions' => ScheduleAction::cases(),
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($agencyQuery) => $agencyQuery->whereKey($user->agency_id))->orderBy('name')->get(),
            'editorialEvents' => EditorialCalendarEvent::query()->visibleTo($user)->whereDate('event_date', '>=', today())->orderBy('event_date')->limit(150)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ScheduleEntry::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('schedules.create', $this->formOptions($user));
    }

    public function store(StoreScheduleEntryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $action = ScheduleAction::from($data['action']);
        $activeKey = $action === ScheduleAction::PublishWordPress ? 'publication:'.$data['publication_id'] : 'campaign:'.$data['campaign_id'].':'.$action->value;
        try {
            $entry = DB::transaction(function () use ($data, $action, $activeKey, $request): ScheduleEntry {
                $resource = $action === ScheduleAction::PublishWordPress ? Publication::query()->lockForUpdate()->findOrFail($data['publication_id']) : Campaign::query()->lockForUpdate()->findOrFail($data['campaign_id']);
                if ($resource->agency_id !== $data['agency_id']) {
                    throw ValidationException::withMessages(['agency_id' => 'Planlanacak kaynak artık bu ajans için kullanılamaz.']);
                }
                if ($resource instanceof Publication && $resource->status !== PublicationStatus::Failed) {
                    throw ValidationException::withMessages(['publication_id' => 'Yayın kaydı artık planlamaya uygun değildir.']);
                }
                if ($resource instanceof Campaign && (($action === ScheduleAction::ActivateCampaign && $resource->status !== CampaignStatus::Scheduled) || ($action === ScheduleAction::CompleteCampaign && ! in_array($resource->status, [CampaignStatus::Active, CampaignStatus::Paused], true)))) {
                    throw ValidationException::withMessages(['campaign_id' => 'Kampanya durumu artık bu plan için uygun değildir.']);
                }
                if (ScheduleEntry::query()->where('active_key', $activeKey)->exists()) {
                    throw ValidationException::withMessages(['scheduled_for' => 'Bu kaynak için zaten aktif bir plan bulunuyor.']);
                }
                $scheduledFor = CarbonImmutable::parse($data['scheduled_for'], $data['timezone'])->setTimezone(config('app.timezone'));

                return ScheduleEntry::query()->create(['agency_id' => $data['agency_id'], 'created_by' => $request->user()->id, 'publication_id' => $action === ScheduleAction::PublishWordPress ? $resource->id : null, 'campaign_id' => $action === ScheduleAction::PublishWordPress ? null : $resource->id, 'action' => $action, 'status' => ScheduleStatus::Pending, 'active_key' => $activeKey, 'title' => $this->title($action, $resource), 'scheduled_for' => $scheduledFor, 'timezone' => $data['timezone']]);
            }, 3);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'active_key')) {
                throw ValidationException::withMessages(['scheduled_for' => 'Bu kaynak için zaten aktif bir plan bulunuyor.']);
            }
            throw $exception;
        }

        return redirect()->route('schedules.show', $entry)->with('success', 'Plan takvime eklendi.');
    }

    public function show(ScheduleEntry $schedule): View
    {
        Gate::authorize('view', $schedule);

        return view('schedules.show', ['entry' => $schedule->load(['agency', 'creator', 'publication.article', 'publication.publishingTarget', 'campaign'])]);
    }

    /** @return array{agencies: Collection<int, Agency>, publications: Collection<int, Publication>, campaigns: Collection<int, Campaign>, actions: array<int, ScheduleAction>} */
    private function formOptions(User $user): array
    {
        return ['agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get(), 'publications' => Publication::query()->visibleTo($user)->where('status', PublicationStatus::Failed)->with(['article', 'publishingTarget'])->orderByDesc('created_at')->limit(200)->get(), 'campaigns' => Campaign::query()->visibleTo($user)->whereIn('status', [CampaignStatus::Scheduled, CampaignStatus::Active, CampaignStatus::Paused])->orderBy('name')->get(), 'actions' => ScheduleAction::cases()];
    }

    private function title(ScheduleAction $action, Publication|Campaign $resource): string
    {
        return $resource instanceof Publication ? $resource->article()->value('title').' → '.$resource->publishingTarget()->withTrashed()->value('name') : $resource->name.' · '.$action->label();
    }
}
