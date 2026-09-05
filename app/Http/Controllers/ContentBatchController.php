<?php

namespace App\Http\Controllers;

use App\ContentBatchStatus;
use App\Http\Requests\StoreContentBatchRequest;
use App\Http\Requests\UpdateContentBatchRequest;
use App\Jobs\ProcessContentBatch;
use App\Models\Agency;
use App\Models\AiPrompt;
use App\Models\ContentBatch;
use App\Models\ContentBatchItem;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContentBatchController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ContentBatch::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));
        $query = ContentBatch::query()->visibleTo($user)->with(['agency', 'creator', 'aiPrompt']);

        if (ContentBatchStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $statusCounts = collect(ContentBatchStatus::cases())->mapWithKeys(fn (ContentBatchStatus $item): array => [
            $item->value => ContentBatch::query()->visibleTo($user)->where('status', $item)->count(),
        ]);

        return view('content-batches.index', [
            'batches' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'statuses' => ContentBatchStatus::cases(),
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ContentBatch::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('content-batches.create', $this->formOptions($user));
    }

    public function store(StoreContentBatchRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $batch = DB::transaction(function () use ($data, $request): ContentBatch {
            $prompt = AiPrompt::query()->lockForUpdate()->find($data['ai_prompt_id']);

            if (! $prompt || ! $prompt->is_active || $prompt->agency_id !== null && $prompt->agency_id !== $data['agency_id']) {
                throw ValidationException::withMessages(['ai_prompt_id' => 'Seçilen prompt artık bu üretim bandı için kullanılamaz.']);
            }
            $rawNewsItems = RawNewsItem::query()
                ->whereIn('id', $data['raw_news_ids'])
                ->lockForUpdate()
                ->get();

            if ($rawNewsItems->count() !== count($data['raw_news_ids']) || $rawNewsItems->contains(fn (RawNewsItem $item): bool => $item->agency_id !== $data['agency_id'] || ! in_array($item->status, [RawNewsStatus::Pending, RawNewsStatus::Failed], true))) {
                throw ValidationException::withMessages(['raw_news_ids' => 'Ham haberlerden biri artık üretime uygun değil; listeyi yenileyip tekrar deneyin.']);
            }

            $batch = ContentBatch::query()->create([
                'agency_id' => $data['agency_id'],
                'created_by' => $request->user()->id,
                'ai_prompt_id' => $prompt->id,
                'name' => $data['name'],
                'status' => ContentBatchStatus::Queued,
                'total_items' => $rawNewsItems->count(),
                'settings' => [
                    'prompt_snapshot' => [
                        'name' => $prompt->name,
                        'version' => $prompt->version,
                        'tone' => $prompt->tone->value,
                        'language' => $prompt->language,
                        'target_length' => $prompt->target_length,
                        'temperature' => $prompt->temperature,
                        'system_prompt' => $prompt->system_prompt,
                        'user_prompt_template' => $prompt->user_prompt_template,
                    ],
                ],
            ]);

            $batch->items()->createMany($rawNewsItems->map(fn (RawNewsItem $item): array => ['raw_news_item_id' => $item->id])->all());
            $rawNewsItems->toQuery()->update(['status' => RawNewsStatus::Queued, 'failure_message' => null]);

            return $batch;
        }, 3);

        ProcessContentBatch::dispatch($batch->id)->onQueue('content')->afterCommit();

        return redirect()->route('content-batches.show', $batch)->with('success', 'Üretim bandı oluşturuldu ve içerik kuyruğuna gönderildi.');
    }

    public function edit(ContentBatch $contentBatch): View
    {
        Gate::authorize('update', $contentBatch);

        return view('content-batches.edit', ['batch' => $contentBatch]);
    }

    public function update(UpdateContentBatchRequest $request, ContentBatch $contentBatch): RedirectResponse
    {
        $contentBatch->update($request->validated());

        return redirect()->route('content-batches.show', $contentBatch)->with('success', 'Üretim bandı güncellendi.');
    }

    public function destroy(ContentBatch $contentBatch): RedirectResponse
    {
        Gate::authorize('delete', $contentBatch);
        DB::transaction(function () use ($contentBatch): void {
            $contentBatch->items()->whereNull('article_id')->with('rawNewsItem')->get()->each(function (ContentBatchItem $item): void {
                if ($item->rawNewsItem && $item->rawNewsItem->status !== RawNewsStatus::Processed) {
                    $item->rawNewsItem->update(['status' => RawNewsStatus::Pending, 'failure_message' => null]);
                }
            });
            $contentBatch->delete();
        }, 5);

        return redirect()->route('content-batches.index')->with('success', 'Üretim bandı silindi; işlenmemiş haberler havuza döndürüldü.');
    }

    public function show(ContentBatch $contentBatch): View
    {
        Gate::authorize('view', $contentBatch);
        $contentBatch->load(['agency', 'creator', 'aiPrompt']);
        $items = ContentBatchItem::query()
            ->whereBelongsTo($contentBatch)
            ->with(['rawNewsItem', 'article'])
            ->orderBy('id')
            ->paginate(25);

        return view('content-batches.show', ['batch' => $contentBatch, 'items' => $items]);
    }

    /**
     * @return array{agencies: Collection<int, Agency>, prompts: Collection<int, AiPrompt>, rawNewsItems: Collection<int, RawNewsItem>}
     */
    private function formOptions(User $user): array
    {
        return [
            'agencies' => Agency::query()->where('is_active', true)
                ->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))
                ->orderBy('name')->get(),
            'prompts' => AiPrompt::query()->visibleTo($user)->where('is_active', true)->with('agency')->orderBy('name')->get(),
            'rawNewsItems' => RawNewsItem::query()->visibleTo($user)
                ->whereIn('status', [RawNewsStatus::Pending, RawNewsStatus::Failed])
                ->with('agency')->orderByDesc('priority')->orderBy('id')->limit(500)->get(),
        ];
    }
}
