<?php

namespace App\Http\Controllers;

use App\AdviceLetterStatus;
use App\AdviceRiskLevel;
use App\Http\Requests\StoreAdviceLetterRequest;
use App\Http\Requests\UpdateAdviceLetterRequest;
use App\Models\AdviceLetter;
use App\Models\Agency;
use App\Models\User;
use App\Services\AdviceLetterRiskAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdviceLetterController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AdviceLetter::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('advice-letters.index', [
            'letters' => AdviceLetter::query()->visibleTo($user)->with('agency')->latest()->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', AdviceLetter::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('advice-letters.create', [
            'agencies' => Agency::query()->where('is_active', true)
                ->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))
                ->orderBy('name')->get(),
        ]);
    }

    public function store(StoreAdviceLetterRequest $request, AdviceLetterRiskAnalyzer $riskAnalyzer): RedirectResponse
    {
        $data = $request->validated();
        $risk = $riskAnalyzer->analyze($data['question']);
        $letter = AdviceLetter::query()->create([
            ...$data,
            'submitted_by' => $request->user()?->id,
            'status' => AdviceLetterStatus::Pending,
            'risk_level' => $risk['level'],
            'risk_flags' => $risk['flags'],
        ]);

        return redirect()->route('advice-letters.show', $letter)->with('success', 'Mektup Mukaddes Abla masasına eklendi.');
    }

    public function show(AdviceLetter $adviceLetter): View
    {
        Gate::authorize('view', $adviceLetter);

        return view('advice-letters.show', ['letter' => $adviceLetter->load('agency')]);
    }

    public function edit(AdviceLetter $adviceLetter): View
    {
        Gate::authorize('update', $adviceLetter);

        return view('advice-letters.edit', ['letter' => $adviceLetter]);
    }

    public function update(UpdateAdviceLetterRequest $request, AdviceLetter $adviceLetter): RedirectResponse
    {
        $data = $request->validated();
        $status = AdviceLetterStatus::from($data['status']);

        if ($adviceLetter->risk_level === AdviceRiskLevel::Critical) {
            throw ValidationException::withMessages(['status' => 'Kritik risk taşıyan mektup otomatik yanıtlanamaz veya yayınlanamaz.']);
        }
        if ($status === AdviceLetterStatus::Published && ! $adviceLetter->publication_consent) {
            throw ValidationException::withMessages(['status' => 'Yayın izni olmayan mektup yayınlanamaz.']);
        }

        $isAnswered = in_array($status, [AdviceLetterStatus::Answered, AdviceLetterStatus::Published], true);
        $adviceLetter->forceFill([
            'status' => $status,
            'response_title' => $data['response_title'],
            'response_body' => $data['response_body'],
            'answered_by' => $isAnswered ? $request->user()?->id : null,
            'answered_at' => $isAnswered ? ($adviceLetter->answered_at ?? now()) : null,
            'published_at' => $status === AdviceLetterStatus::Published ? ($adviceLetter->published_at ?? now()) : null,
        ])->save();

        return redirect()->route('advice-letters.show', $adviceLetter)->with('success', 'Mukaddes Abla yanıtı güncellendi.');
    }

    public function destroy(AdviceLetter $adviceLetter): RedirectResponse
    {
        Gate::authorize('delete', $adviceLetter);
        $adviceLetter->delete();

        return redirect()->route('advice-letters.index')->with('success', 'Mektup silindi.');
    }
}
