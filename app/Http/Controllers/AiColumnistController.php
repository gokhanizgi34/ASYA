<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiColumnistRequest;
use App\Http\Requests\UpdateAiColumnistRequest;
use App\Models\Agency;
use App\Models\AiColumnist;
use App\Models\AiPrompt;
use App\Models\ColumnistDraft;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AiColumnistController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AiColumnist::class);
        $u = $request->user();
        abort_unless($u instanceof User, 401);

        return view('ai-columnists.index', ['columnists' => AiColumnist::query()->visibleTo($u)->with(['agency', 'aiPrompt'])->orderBy('pen_name')->get(), 'drafts' => ColumnistDraft::query()->visibleTo($u)->with('columnist')->latest()->limit(20)->get()]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', AiColumnist::class);
        $u = $request->user();
        abort_unless($u instanceof User, 401);

        return view('ai-columnists.create', $this->options($u));
    }

    public function store(StoreAiColumnistRequest $request): RedirectResponse
    {
        AiColumnist::create([...$request->validated(), 'created_by' => $request->user()?->id]);

        return redirect()->route('ai-columnists.index')->with('success', 'AI köşe yazarı oluşturuldu.');
    }

    public function edit(Request $request, AiColumnist $aiColumnist): View
    {
        Gate::authorize('update', $aiColumnist);
        $u = $request->user();
        abort_unless($u instanceof User, 401);

        return view('ai-columnists.edit', ['columnist' => $aiColumnist, ...$this->options($u)]);
    }

    public function update(UpdateAiColumnistRequest $request, AiColumnist $aiColumnist): RedirectResponse
    {
        $aiColumnist->update($request->validated());

        return redirect()->route('ai-columnists.index')->with('success', 'Köşe yazarı güncellendi.');
    }

    /** @return array<string,mixed> */
    private function options(User $u): array
    {
        return ['agencies' => Agency::query()->where('is_active', true)->when(! $u->isSystemAdministrator(), fn ($q) => $q->whereKey($u->agency_id))->get(), 'prompts' => AiPrompt::query()->visibleTo($u)->where('is_active', true)->get()];
    }
}
