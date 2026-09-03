<?php

namespace App\Http\Controllers;

use App\CopyrightStatus;
use App\Http\Requests\StoreVisualAssetRequest;
use App\Models\Agency;
use App\Models\Article;
use App\Models\User;
use App\Models\VisualAsset;
use App\Services\SystemSettings;
use App\Services\VisualAssetEvaluator;
use App\VisualAssetStatus;
use App\VisualSourceType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

class VisualAssetController extends Controller
{
    public function index(Request $request, SystemSettings $settings): View
    {
        Gate::authorize('viewAny', VisualAsset::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $query = VisualAsset::query()->visibleTo($user)->with(['agency', 'article', 'uploader']);
        $status = (string) $request->query('status', '');
        $sourceType = (string) $request->query('source_type', '');
        $search = trim((string) $request->query('q', ''));

        if (VisualAssetStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        if (VisualSourceType::tryFrom($sourceType)) {
            $query->where('source_type', $sourceType);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%")
                    ->orWhere('headline_overlay', 'like', "%{$search}%");
            });
        }

        $statusCounts = collect(VisualAssetStatus::cases())->mapWithKeys(fn (VisualAssetStatus $item): array => [
            $item->value => VisualAsset::query()->visibleTo($user)->where('status', $item)->count(),
        ]);

        $agencies = $this->agencies($user);
        $settingsAgencyId = $this->settingsAgencyId($request, $user, $agencies);

        return view('visual-assets.index', [
            'assets' => $query->latest()->paginate(12)->withQueryString(),
            'statuses' => VisualAssetStatus::cases(),
            'sourceTypes' => VisualSourceType::cases(),
            'statusCounts' => $statusCounts,
            'settingAgencies' => $agencies,
            'settingsAgencyId' => $settingsAgencyId,
            'aiGenerationEnabled' => (bool) $settings->get('visual.ai_generation_enabled', $settingsAgencyId),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', VisualAsset::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('visual-assets.create', $this->formOptions($user));
    }

    public function store(StoreVisualAssetRequest $request, VisualAssetEvaluator $evaluator): RedirectResponse
    {
        $data = $request->validated();
        unset($data['image']);
        $data['uploaded_by'] = $request->user()->id;
        $data['storage_disk'] = 'public';

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $dimensions = getimagesize($image->getRealPath());
            $path = $image->store('visual-assets/'.$data['agency_id'], 'public');

            if (! is_string($path)) {
                throw new RuntimeException('Görsel dosyası depolanamadı.');
            }

            $data['storage_path'] = $path;
            $data['mime_type'] = $image->getMimeType();
            $data['width'] = is_array($dimensions) ? $dimensions[0] : null;
            $data['height'] = is_array($dimensions) ? $dimensions[1] : null;
        }

        $asset = VisualAsset::query()->create($data);
        $asset->update($evaluator->evaluate($asset));

        return redirect()->route('visual-assets.show', $asset)->with('success', 'Görsel kaydedildi ve kalite/telif değerlendirmesi tamamlandı.');
    }

    public function show(VisualAsset $visualAsset): View
    {
        Gate::authorize('view', $visualAsset);

        return view('visual-assets.show', ['asset' => $visualAsset->load(['agency', 'article', 'uploader'])]);
    }

    public function destroy(VisualAsset $visualAsset): RedirectResponse
    {
        Gate::authorize('delete', $visualAsset);
        $visualAsset->delete();

        return redirect()->route('visual-assets.index')->with('success', 'Görsel kaydı geri alınabilir şekilde silindi.');
    }

    /** @return Collection<int, Agency> */
    private function agencies(User $user): Collection
    {
        return Agency::query()
            ->where('is_active', true)
            ->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))
            ->orderBy('name')
            ->get();
    }

    /** @param Collection<int, Agency> $agencies */
    private function settingsAgencyId(Request $request, User $user, Collection $agencies): ?int
    {
        if (! $user->isSystemAdministrator()) {
            return $user->agency_id;
        }

        $requestedAgencyId = $request->integer('agency_id');

        return $agencies->contains('id', $requestedAgencyId)
            ? $requestedAgencyId
            : $agencies->first()?->id;
    }

    /**
     * @return array{agencies: Collection<int, Agency>, articles: Collection<int, Article>, sourceTypes: array<int, VisualSourceType>, copyrightStatuses: array<int, CopyrightStatus>}
     */
    private function formOptions(User $user): array
    {
        return [
            'agencies' => Agency::query()->where('is_active', true)
                ->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))
                ->orderBy('name')->get(),
            'articles' => Article::query()->visibleTo($user)->with('agency')->orderByDesc('created_at')->limit(200)->get(),
            'sourceTypes' => VisualSourceType::cases(),
            'copyrightStatuses' => CopyrightStatus::cases(),
        ];
    }
}
