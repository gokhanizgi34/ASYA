<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiPromptRequest;
use App\Http\Requests\UpdateAiPromptRequest;
use App\Models\Agency;
use App\Models\AiPrompt;
use App\Models\User;
use App\PromptTone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AiPromptController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AiPrompt::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $query = AiPrompt::query()->visibleTo($user)->with('agency');
        $search = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $active = (string) $request->query('active', '');

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('system_prompt', 'like', "%{$search}%");
            });
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        if (in_array($active, ['0', '1'], true)) {
            $query->where('is_active', $active === '1');
        }

        return view('prompts.index', [
            'prompts' => $query->orderByRaw('agency_id IS NOT NULL')->orderBy('name')->paginate(15)->withQueryString(),
            'categories' => AiPrompt::query()->visibleTo($user)->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', AiPrompt::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('prompts.create', $this->formOptions($user));
    }

    public function store(StoreAiPromptRequest $request): RedirectResponse
    {
        AiPrompt::query()->create($request->validated());

        return redirect()->route('prompts.index')->with('success', 'Prompt şablonu oluşturuldu.');
    }

    public function edit(Request $request, AiPrompt $aiPrompt): View
    {
        Gate::authorize('update', $aiPrompt);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('prompts.edit', [
            'prompt' => $aiPrompt,
            ...$this->formOptions($user, $aiPrompt),
        ]);
    }

    public function update(UpdateAiPromptRequest $request, AiPrompt $aiPrompt): RedirectResponse
    {
        $aiPrompt->update([
            ...$request->validated(),
            'version' => $aiPrompt->version + 1,
        ]);

        return redirect()->route('prompts.index')->with('success', 'Prompt şablonu güncellendi ve sürümü artırıldı.');
    }

    public function destroy(AiPrompt $aiPrompt): RedirectResponse
    {
        Gate::authorize('delete', $aiPrompt);
        $aiPrompt->delete();

        return redirect()->route('prompts.index')->with('success', 'Prompt şablonu geri alınabilir şekilde silindi.');
    }

    /**
     * @return array{agencies: Collection<int, Agency>, tones: array<int, PromptTone>, categories: array<int, string>}
     */
    private function formOptions(User $user, ?AiPrompt $prompt = null): array
    {
        $agencies = Agency::query()
            ->where(function ($query) use ($prompt): void {
                $query->where('is_active', true);

                if ($prompt?->agency_id !== null) {
                    $query->orWhereKey($prompt->agency_id);
                }
            })
            ->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))
            ->orderBy('name')
            ->get();

        return [
            'agencies' => $agencies,
            'tones' => PromptTone::cases(),
            'categories' => ['haber', 'seo', 'sosyal_medya', 'kose_yazisi', 'ceviri'],
        ];
    }
}
