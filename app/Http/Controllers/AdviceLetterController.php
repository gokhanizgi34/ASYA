<?php

namespace App\Http\Controllers;

use App\AdviceLetterStatus;
use App\AdviceRiskLevel;
use App\Http\Requests\StoreAdviceLetterRequest;
use App\Http\Requests\UpdateAdviceLetterRequest;
use App\Models\AdviceLetter;
use App\Models\Agency;
use App\Models\User;
use App\Services\AdviceSafetyAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdviceLetterController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AdviceLetter::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $query = AdviceLetter::query()->visibleTo($user)->with(['agency', 'submitter', 'answerer']);
        $status = AdviceLetterStatus::tryFrom((string) $request->query('status'));
        $risk = AdviceRiskLevel::tryFrom((string) $request->query('risk'));
        $category = trim((string) $request->query('category'));

        if ($status) {
            $query->where('status', $status);
        }
        if ($risk) {
            $query->where('risk_level', $risk);
        }
        if (in_array($category, array_keys($this->categories()), true)) {
            $query->where('category', $category);
        }

        return view('advice-letters.index', [
            'letters' => $query->latest()->paginate(30)->withQueryString(),
            'statuses' => AdviceLetterStatus::cases(),
            'risks' => AdviceRiskLevel::cases(),
            'categories' => $this->categories(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', AdviceLetter::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('advice-letters.create', [
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get(),
            'categories' => $this->categories(),
        ]);
    }

    public function store(StoreAdviceLetterRequest $request, AdviceSafetyAnalyzer $analyzer): RedirectResponse
    {
        $analysis = $analyzer->analyze($request->validated('question'));
        $letter = AdviceLetter::query()->create([
            ...$request->validated(),
            'submitted_by' => $request->user()?->id,
            'status' => AdviceLetterStatus::Pending,
            'risk_level' => $analysis['risk_level'],
            'risk_flags' => $analysis['flags'],
        ]);

        return redirect()->route('advice-letters.show', $letter)->with('success', 'Mektup güvenlik taramasından geçirilerek Mukaddes Abla ekranına alındı.');
    }

    public function show(AdviceLetter $adviceLetter): View
    {
        Gate::authorize('view', $adviceLetter);

        return view('advice-letters.show', ['letter' => $adviceLetter->load(['agency', 'submitter', 'answerer']), 'categories' => $this->categories()]);
    }

    public function edit(AdviceLetter $adviceLetter): View
    {
        Gate::authorize('update', $adviceLetter);

        return view('advice-letters.edit', ['letter' => $adviceLetter, 'statuses' => AdviceLetterStatus::cases()]);
    }

    public function update(UpdateAdviceLetterRequest $request, AdviceLetter $adviceLetter): RedirectResponse
    {
        $data = $request->validated();
        $status = AdviceLetterStatus::from($data['status']);
        $isAnswered = in_array($status, [AdviceLetterStatus::Answered, AdviceLetterStatus::Published], true);

        $adviceLetter->update([
            ...$data,
            'answered_by' => $isAnswered ? $request->user()?->id : $adviceLetter->answered_by,
            'answered_at' => $isAnswered ? ($adviceLetter->answered_at ?? now()) : $adviceLetter->answered_at,
            'published_at' => $status === AdviceLetterStatus::Published ? ($adviceLetter->published_at ?? now()) : null,
        ]);

        return redirect()->route('advice-letters.show', $adviceLetter)->with('success', 'Danışma mektubu güncellendi.');
    }

    /** @return array<string, string> */
    private function categories(): array
    {
        return [
            'relationship' => 'İlişkiler',
            'family' => 'Aile',
            'work' => 'İş hayatı',
            'personal' => 'Kişisel gelişim',
            'other' => 'Diğer',
        ];
    }
}
