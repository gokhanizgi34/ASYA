<?php

namespace App\Http\Controllers;

use App\ColumnistDraftStatus;
use App\Http\Requests\StoreColumnistDraftRequest;
use App\Http\Requests\UpdateColumnistDraftRequest;
use App\Models\AiColumnist;
use App\Models\ColumnistDraft;
use App\Models\User;
use App\Services\ColumnistDraftComposer;
use App\Services\GeneratedContentPublicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ColumnistDraftController extends Controller
{
    public function create(Request $request): View
    {
        Gate::authorize('create', ColumnistDraft::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('ai-columnists.draft-create', ['columnists' => AiColumnist::query()->visibleTo($user)->where('is_active', true)->get()]);
    }

    public function store(StoreColumnistDraftRequest $request, ColumnistDraftComposer $composer): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $columnist = AiColumnist::query()->findOrFail($request->integer('ai_columnist_id'));
        $composed = $composer->compose($columnist, $request->validated('topic'), $request->validated('source_notes'));
        $draft = ColumnistDraft::query()->create(['agency_id' => $columnist->agency_id, 'ai_columnist_id' => $columnist->id, 'created_by' => $user->id, 'topic' => $request->validated('topic'), 'source_notes' => $request->validated('source_notes'), ...$composed, 'status' => ColumnistDraftStatus::Draft]);

        return redirect()->route('columnist-drafts.show', $draft)->with('success', 'Köşe yazısı taslağı oluşturuldu; onaylandığında Yayın Merkezi’ne gönderilecek.');
    }

    public function show(ColumnistDraft $columnistDraft): View
    {
        Gate::authorize('view', $columnistDraft);

        return view('ai-columnists.draft-show', ['draft' => $columnistDraft->load(['columnist', 'creator', 'reviewer']), 'statuses' => ColumnistDraftStatus::cases()]);
    }

    public function update(UpdateColumnistDraftRequest $request, ColumnistDraft $columnistDraft, GeneratedContentPublicationService $publisher): RedirectResponse
    {
        $status = ColumnistDraftStatus::from($request->validated('status'));
        $reviewed = in_array($status, [ColumnistDraftStatus::Approved, ColumnistDraftStatus::Rejected], true);
        $columnistDraft->update([...$request->validated(), 'reviewed_by' => $reviewed ? $request->user()?->id : null, 'reviewed_at' => $reviewed ? now() : null]);

        if ($status === ColumnistDraftStatus::Approved && $request->user() instanceof User) {
            $publisher->send($columnistDraft->agency_id, $request->user(), [
                'title' => $columnistDraft->headline, 'summary' => Str::limit(strip_tags($columnistDraft->body), 155, ''),
                'body' => $columnistDraft->body, 'keywords' => [$columnistDraft->topic, $columnistDraft->columnist?->pen_name],
                'hashtags' => ['#KöşeYazısı'], 'category' => 'Köşe Yazıları', 'source_type' => 'columnist',
                'source_id' => $columnistDraft->id, 'slug' => Str::slug($columnistDraft->headline).'-kose-'.$columnistDraft->id, 'destination' => 'publish',
            ]);
        }

        return back()->with('success', $status === ColumnistDraftStatus::Approved ? 'Köşe yazısı onaylandı ve Yayın Merkezi’ne gönderildi.' : 'Köşe taslağı güncellendi.');
    }
}
