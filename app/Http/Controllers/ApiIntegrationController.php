<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApiIntegrationRequest;
use App\Http\Requests\UpdateApiIntegrationRequest;
use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\MailTransportScheme;
use App\Models\Agency;
use App\Models\AgencyMailSetting;
use App\Models\ApiIntegration;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApiIntegrationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ApiIntegration::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('api-integrations.index', [
            'integrations' => ApiIntegration::query()->visibleTo($user)->with('agency')->orderByDesc('is_default')->orderBy('priority')->orderBy('name')->paginate(30),
            'mailSettings' => AgencyMailSetting::query()->visibleTo($user)->with('agency')->orderBy('agency_id')->get(),
            'mailAgencies' => $this->agencies($user),
            'mailSchemes' => MailTransportScheme::cases(),
            'aiProviders' => collect(IntegrationProvider::cases())->filter(fn (IntegrationProvider $provider): bool => $provider->isAi() || $provider === IntegrationProvider::XTrends)->values(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ApiIntegration::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('api-integrations.create', $this->formOptions($user));
    }

    public function store(StoreApiIntegrationRequest $request): RedirectResponse
    {
        $this->persist($request->validated());

        return redirect()->route('api-integrations.index')->with('success', 'API entegrasyonu oluşturuldu.');
    }

    public function edit(Request $request, ApiIntegration $apiIntegration): View
    {
        Gate::authorize('update', $apiIntegration);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('api-integrations.edit', [
            'integration' => $apiIntegration,
            ...$this->formOptions($user, $apiIntegration),
        ]);
    }

    public function update(UpdateApiIntegrationRequest $request, ApiIntegration $apiIntegration): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['credential'] ?? null)) {
            unset($data['credential']);
        }

        $this->persist($data, $apiIntegration);

        return redirect()->route('api-integrations.index')->with('success', 'API entegrasyonu güncellendi.');
    }

    public function destroy(ApiIntegration $apiIntegration): RedirectResponse
    {
        Gate::authorize('delete', $apiIntegration);
        $apiIntegration->delete();

        return redirect()->route('api-integrations.index')->with('success', 'API entegrasyonu geri alınabilir şekilde silindi.');
    }

    /** @param array<string, mixed> $data */
    private function persist(array $data, ?ApiIntegration $integration = null): ApiIntegration
    {
        return DB::transaction(function () use ($data, $integration): ApiIntegration {
            $provider = IntegrationProvider::from($data['provider']);

            if ($provider->isAi() && ! $integration && ! ApiIntegration::query()->where('agency_id', $data['agency_id'])->ai()->where('is_default', true)->exists()) {
                $data['is_default'] = true;
            }

            if ($provider->isAi() && $data['is_default']) {
                ApiIntegration::query()
                    ->where('agency_id', $data['agency_id'])
                    ->ai()
                    ->when($integration, fn ($query) => $query->whereKeyNot($integration->id))
                    ->update(['is_default' => false]);
            }

            if ($integration) {
                $integration->update($data);

                return $integration->refresh();
            }

            $deletedIntegration = ApiIntegration::withTrashed()
                ->where('agency_id', $data['agency_id'])
                ->where('name', $data['name'])
                ->first();

            if ($deletedIntegration?->trashed()) {
                $deletedIntegration->restore();
                $deletedIntegration->update($data);

                return $deletedIntegration->refresh();
            }

            return ApiIntegration::query()->create($data);
        });
    }

    /** @return array{agencies: Collection<int, Agency>, providers: array<int, IntegrationProvider>, aiProviders: array<int, IntegrationProvider>, authTypes: array<int, IntegrationAuthType>} */
    private function formOptions(User $user, ?ApiIntegration $integration = null): array
    {
        return [
            'agencies' => $this->agencies($user, $integration?->agency_id),
            'providers' => IntegrationProvider::cases(),
            'aiProviders' => array_values(array_filter(IntegrationProvider::cases(), fn (IntegrationProvider $provider): bool => $provider->isAi() || $provider === IntegrationProvider::XTrends)),
            'authTypes' => IntegrationAuthType::cases(),
        ];
    }

    /** @return Collection<int, Agency> */
    private function agencies(User $user, ?int $currentAgencyId = null): Collection
    {
        return Agency::query()
            ->where(function ($query) use ($currentAgencyId): void {
                $query->where('is_active', true);
                if ($currentAgencyId) {
                    $query->orWhereKey($currentAgencyId);
                }
            })
            ->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))
            ->orderBy('name')
            ->get();
    }
}
