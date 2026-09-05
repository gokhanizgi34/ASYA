<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublishingTargetRequest;
use App\Http\Requests\UpdatePublishingTargetRequest;
use App\Models\Agency;
use App\Models\PublishingTarget;
use App\Models\User;
use App\PublicationStatus;
use App\PublishingProtocol;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PublishingTargetController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PublishingTarget::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('publishing-targets.index', [
            'targets' => PublishingTarget::query()->visibleTo($user)->with('agency')->withCount('publications')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', PublishingTarget::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('publishing-targets.create', $this->formOptions($user));
    }

    public function store(StorePublishingTargetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $deletedTarget = PublishingTarget::withTrashed()
            ->where('agency_id', $data['agency_id'])
            ->where('base_url', $data['base_url'])
            ->first();

        if ($deletedTarget?->trashed()) {
            $deletedTarget->restore();
            $deletedTarget->update($data);
        } else {
            PublishingTarget::query()->create($data);
        }

        return redirect()->route('publishing-targets.index')->with('success', 'WordPress yayın hedefi oluşturuldu.');
    }

    public function edit(Request $request, PublishingTarget $publishingTarget): View
    {
        Gate::authorize('update', $publishingTarget);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('publishing-targets.edit', [
            'target' => $publishingTarget,
            ...$this->formOptions($user, $publishingTarget),
        ]);
    }

    public function update(UpdatePublishingTargetRequest $request, PublishingTarget $publishingTarget): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['credential'] ?? null)) {
            unset($data['credential']);
        }

        $publishingTarget->update($data);

        return redirect()->route('publishing-targets.index')->with('success', 'Yayın hedefi güncellendi.');
    }

    public function destroy(PublishingTarget $publishingTarget): RedirectResponse
    {
        Gate::authorize('delete', $publishingTarget);

        if ($publishingTarget->publications()->where('status', PublicationStatus::Publishing)->exists()) {
            return redirect()->route('publishing-targets.index')->with('error', 'Hedef şu anda yayın yaptığı için silinemedi. İşlem tamamlandıktan sonra tekrar deneyin.');
        }

        DB::transaction(function () use ($publishingTarget): void {
            $publishingTarget->publications()->where('status', PublicationStatus::Queued)->update([
                'status' => PublicationStatus::Failed,
                'failure_message' => 'Yayın hedefi silindiği için kuyruktan çıkarıldı.',
                'completed_at' => now(),
            ]);
            $publishingTarget->delete();
        }, 3);

        return redirect()->route('publishing-targets.index')->with('success', 'Yayın hedefi silindi; bekleyen yayınlar kuyruktan çıkarıldı.');
    }

    /** @return array{agencies: Collection<int, Agency>, protocols: array<int, PublishingProtocol>} */
    private function formOptions(User $user, ?PublishingTarget $target = null): array
    {
        return [
            'agencies' => Agency::query()->where(function ($query) use ($target): void {
                $query->where('is_active', true);
                if ($target?->agency_id) {
                    $query->orWhereKey($target->agency_id);
                }
            })->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get(),
            'protocols' => PublishingProtocol::cases(),
        ];
    }
}
