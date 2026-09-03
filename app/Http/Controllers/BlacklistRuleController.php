<?php

namespace App\Http\Controllers;

use App\BlacklistAction;
use App\BlacklistRuleType;
use App\Http\Requests\StoreBlacklistRuleRequest;
use App\Http\Requests\UpdateBlacklistRuleRequest;
use App\Models\Agency;
use App\Models\BlacklistRule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BlacklistRuleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', BlacklistRule::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $query = BlacklistRule::query()->visibleTo($user)->with(['agency', 'creator']);
        $type = BlacklistRuleType::tryFrom((string) $request->query('type'));
        $action = BlacklistAction::tryFrom((string) $request->query('action'));
        $search = trim((string) $request->query('q'));

        if ($type) {
            $query->where('type', $type);
        }

        if ($action) {
            $query->where('action', $action);
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($search !== '') {
            $query->where(fn ($query) => $query->where('pattern', 'like', "%{$search}%")->orWhere('reason', 'like', "%{$search}%"));
        }

        return view('blacklist-rules.index', [
            'rules' => $query->orderByDesc('is_active')->latest()->paginate(30)->withQueryString(),
            'types' => BlacklistRuleType::cases(),
            'actions' => BlacklistAction::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', BlacklistRule::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('blacklist-rules.create', $this->formOptions($user));
    }

    public function store(StoreBlacklistRuleRequest $request): RedirectResponse
    {
        BlacklistRule::query()->create([...$request->validated(), 'created_by' => $request->user()?->id]);

        return redirect()->route('blacklist-rules.index')->with('success', 'Kara liste kuralı oluşturuldu.');
    }

    public function edit(Request $request, BlacklistRule $blacklistRule): View
    {
        Gate::authorize('update', $blacklistRule);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('blacklist-rules.edit', ['rule' => $blacklistRule, ...$this->formOptions($user)]);
    }

    public function update(UpdateBlacklistRuleRequest $request, BlacklistRule $blacklistRule): RedirectResponse
    {
        $blacklistRule->update($request->validated());

        return redirect()->route('blacklist-rules.index')->with('success', 'Kara liste kuralı güncellendi.');
    }

    public function destroy(BlacklistRule $blacklistRule): RedirectResponse
    {
        Gate::authorize('delete', $blacklistRule);
        $blacklistRule->delete();

        return redirect()->route('blacklist-rules.index')->with('success', 'Kara liste kuralı silindi.');
    }

    /** @return array<string, mixed> */
    private function formOptions(User $user): array
    {
        return [
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get(),
            'types' => BlacklistRuleType::cases(),
            'actions' => BlacklistAction::cases(),
        ];
    }
}
