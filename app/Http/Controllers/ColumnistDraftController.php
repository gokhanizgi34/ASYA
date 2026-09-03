<?php

namespace App\Http\Controllers;

use App\ColumnistDraftStatus;
use App\Http\Requests\StoreColumnistDraftRequest;
use App\Http\Requests\UpdateColumnistDraftRequest;
use App\Models\AiColumnist;
use App\Models\ColumnistDraft;
use App\Models\User;
use App\Services\ColumnistDraftComposer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ColumnistDraftController extends Controller
{
    public function create(Request $request): View
    {
        Gate::authorize('create', ColumnistDraft::class);
        $u = $request->user();
        abort_unless($u instanceof User, 401);

        return view('ai-columnists.draft-create', ['columnists' => AiColumnist::query()->visibleTo($u)->where('is_active', true)->get()]);
    }

    public function store(StoreColumnistDraftRequest $request, ColumnistDraftComposer $composer): RedirectResponse
    {
        $u = $request->user();
        abort_unless($u instanceof User, 401);
        $c = AiColumnist::findOrFail($request->integer('ai_columnist_id'));
        $composed = $composer->compose($c, $request->validated('topic'), $request->validated('source_notes'));
        $d = ColumnistDraft::create(['agency_id' => $c->agency_id, 'ai_columnist_id' => $c->id, 'created_by' => $u->id, 'topic' => $request->validated('topic'), 'source_notes' => $request->validated('source_notes'), ...$composed, 'status' => ColumnistDraftStatus::Draft]);

        return redirect()->route('columnist-drafts.show', $d)->with('success', 'Yerel köşe taslağı oluşturuldu; editoryal inceleme zorunludur.');
    }

    public function show(ColumnistDraft $columnistDraft): View
    {
        Gate::authorize('view', $columnistDraft);

        return view('ai-columnists.draft-show', ['draft' => $columnistDraft->load(['columnist', 'creator', 'reviewer']), 'statuses' => ColumnistDraftStatus::cases()]);
    }

    public function update(UpdateColumnistDraftRequest $request, ColumnistDraft $columnistDraft): RedirectResponse
    {
        $s = ColumnistDraftStatus::from($request->validated('status'));
        $reviewed = in_array($s, [ColumnistDraftStatus::Approved, ColumnistDraftStatus::Rejected], true);
        $columnistDraft->update([...$request->validated(), 'reviewed_by' => $reviewed ? $request->user()?->id : null, 'reviewed_at' => $reviewed ? now() : null]);

        return back()->with('success','Köşe taslağı güncellendi.');
    }
}
