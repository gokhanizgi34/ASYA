<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Agency;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query()->with('agency')->orderBy('name')->orderBy('id');

        if (! $request->user()->isSystemAdministrator()) {
            $query->where('agency_id', $request->user()->agency_id);
        }

        return view('users.index', ['users' => $query->paginate(15)]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', $this->formOptions($request->user()));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::query()->create($request->validated());

        return redirect()->route('users.index')->with('success', 'Kullanıcı başarıyla oluşturuldu.');
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            ...$this->formOptions($request->user(), $user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Kullanıcı bilgileri güncellendi.');
    }

    /**
     * @return array{roles: array<int, UserRole>, agencies: Collection<int, Agency>}
     */
    private function formOptions(User $currentUser, ?User $editedUser = null): array
    {
        $roles = $currentUser->isSystemAdministrator() ? UserRole::cases() : [UserRole::Editor];
        $agencies = Agency::query()
            ->where(function ($query) use ($editedUser): void {
                $query->where('is_active', true);

                if ($editedUser?->agency_id) {
                    $query->orWhereKey($editedUser->agency_id);
                }
            })
            ->when(! $currentUser->isSystemAdministrator(), fn ($query) => $query->whereKey($currentUser->agency_id))
            ->orderBy('name')
            ->get();

        return compact('roles', 'agencies');
    }
}
