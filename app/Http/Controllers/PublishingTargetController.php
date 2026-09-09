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
use Illuminate\Validation\ValidationException;
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
            'canCreateTarget' => $user->can('create', PublishingTarget::class)
                && ($user->isSystemAdministrator() || ! PublishingTarget::query()->visibleTo($user)->exists()),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', PublishingTarget::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        if (! $user->isSystemAdministrator() && PublishingTarget::query()->visibleTo($user)->exists()) {
            return redirect()->route('publishing-targets.index')->with('error', 'Her ajans yalnızca bir WordPress yayın hedefi ekleyebilir. Mevcut hedefi düzenleyin.');
        }

        return view('publishing-targets.create', $this->formOptions($user));
    }

    public function store(StorePublishingTargetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        DB::transaction(function () use ($data, $user): void {
            Agency::query()->lockForUpdate()->findOrFail($data['agency_id']);
            $existingTarget = $user->isSystemAdministrator()
                ? PublishingTarget::withTrashed()->where('base_url', $data['base_url'])->first()
                : PublishingTarget::withTrashed()->where('agency_id', $data['agency_id'])->first();

            if ($existingTarget && ! $existingTarget->trashed()) {
                $field = $user->isSystemAdministrator() ? 'base_url' : 'agency_id';
                throw ValidationException::withMessages([
                    $field => $user->isSystemAdministrator()
                        ? 'Bu site zaten bir WordPress yayın hedefi olarak kayıtlıdır.'
                        : 'Her ajans yalnızca bir WordPress yayın hedefi ekleyebilir. Mevcut hedefi düzenleyin.',
                ]);
            }

            if ($user->isSystemAdministrator() && $existingTarget?->trashed() && $existingTarget->agency_id !== $data['agency_id']) {
                throw ValidationException::withMessages([
                    'base_url' => 'Bu site daha önce başka bir ajansa bağlanmış. Eski hedefi geri yükleyip aynı ajans altında kullanın.',
                ]);
            }

            if ($existingTarget?->trashed()) {
                $existingTarget->restore();
                $existingTarget->update($data);

                return;
            }

            PublishingTarget::query()->create($data);
        }, 3);

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
