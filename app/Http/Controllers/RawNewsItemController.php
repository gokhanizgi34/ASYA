<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRawNewsItemRequest;
use App\Http\Requests\UpdateRawNewsItemRequest;
use App\Models\Agency;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use App\Services\BlacklistMatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RawNewsItemController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RawNewsItem::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $query = RawNewsItem::query()->visibleTo($user)->with('agency');
        $status = (string) $request->query('status', '');
        $language = trim((string) $request->query('language', ''));
        $search = trim((string) $request->query('q', ''));

        if (RawNewsStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        if ($language !== '') {
            $query->where('language', $language);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('original_title', 'like', "%{$search}%")
                    ->orWhere('source_name', 'like', "%{$search}%")
                    ->orWhere('original_body', 'like', "%{$search}%");
            });
        }

        $statusCounts = collect(RawNewsStatus::cases())->mapWithKeys(fn (RawNewsStatus $item): array => [
            $item->value => RawNewsItem::query()->visibleTo($user)->where('status', $item)->count(),
        ]);
        $languages = RawNewsItem::query()->visibleTo($user)->distinct()->orderBy('language')->pluck('language');

        return view('raw-news.index', [
            'items' => $query->orderByDesc('priority')->latest('discovered_at')->paginate(20)->withQueryString(),
            'statuses' => RawNewsStatus::cases(),
            'statusCounts' => $statusCounts,
            'languages' => $languages,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', RawNewsItem::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('raw-news.create', ['agencies' => $this->agenciesFor($user)]);
    }

    public function store(StoreRawNewsItemRequest $request, BlacklistMatcher $matcher): RedirectResponse
    {
        $validated = $request->validated();
        $result = $matcher->evaluate((int) $validated['agency_id'], [
            'title' => $validated['original_title'],
            'body' => $validated['original_body'],
            'source_name' => $validated['source_name'],
            'source_url' => $validated['source_url'],
        ]);

        $status = match (true) {
            $result['blocked'] => RawNewsStatus::Rejected,
            $result['requires_review'] => RawNewsStatus::Review,
            default => RawNewsStatus::Pending,
        };
        $matchedPatterns = $result['matches']->pluck('pattern')->take(5)->implode(', ');

        $item = RawNewsItem::query()->create([
            ...$validated,
            'status' => $status,
            'failure_message' => $matchedPatterns !== '' ? 'Kara liste eşleşmesi: '.$matchedPatterns : null,
            'discovered_at' => now(),
        ]);

        $message = match ($status) {
            RawNewsStatus::Rejected => 'Ham haber kara liste kuralıyla engellendi.',
            RawNewsStatus::Review => 'Ham haber kara liste incelemesine gönderildi.',
            default => 'Ham haber havuza eklendi.',
        };

        return redirect()->route('raw-news.show', $item)->with('success', $message);
    }

    public function show(RawNewsItem $rawNewsItem): View
    {
        Gate::authorize('view', $rawNewsItem);

        return view('raw-news.show', ['item' => $rawNewsItem->load('agency')]);
    }

    public function edit(RawNewsItem $rawNewsItem): View
    {
        Gate::authorize('update', $rawNewsItem);

        return view('raw-news.edit', ['item' => $rawNewsItem]);
    }

    public function update(UpdateRawNewsItemRequest $request, RawNewsItem $rawNewsItem): RedirectResponse
    {
        $rawNewsItem->update([...$request->validated(), 'status' => RawNewsStatus::Pending, 'failure_message' => null, 'processed_at' => null]);

        return redirect()->route('raw-news.show', $rawNewsItem)->with('success', 'Ham haber güncellendi ve yeniden işlenmeye hazırlandı.');
    }

    public function destroy(RawNewsItem $rawNewsItem): RedirectResponse
    {
        Gate::authorize('delete', $rawNewsItem);
        $rawNewsItem->delete();

        return redirect()->route('raw-news.index')->with('success', 'Ham haber geri alınabilir şekilde silindi.');
    }

    /** @return Collection<int, Agency> */
    private function agenciesFor(User $user): Collection
    {
        return Agency::query()->where('is_active', true)
            ->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))
            ->orderBy('name')->get();
    }
}
