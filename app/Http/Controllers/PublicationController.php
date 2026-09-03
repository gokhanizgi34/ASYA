<?php

namespace App\Http\Controllers;

use App\ArticleStatus;
use App\Http\Requests\StorePublicationRequest;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\User;
use App\PublicationStatus;
use App\RemotePublicationStatus;
use App\Services\PublicationCreator;
use App\SourceTrustStatus;
use App\VisualAssetStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PublicationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Publication::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $status = (string) $request->query('status', '');
        $query = Publication::query()->visibleTo($user)->with(['agency', 'article', 'publishingTarget', 'creator']);

        if (PublicationStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        return view('publications.index', [
            'publications' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString(),
            'statuses' => PublicationStatus::cases(),
            'failedPublicationCount' => Publication::query()->visibleTo($user)->where('status', PublicationStatus::Failed)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Publication::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('publications.create', $this->formOptions($user));
    }

    public function store(StorePublicationRequest $request, PublicationCreator $creator): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $publication = DB::transaction(
            fn (): Publication => $creator->create($data, $user),
            3,
        );

        $isScheduled = filled($data['scheduled_for'] ?? null);
        if (! $isScheduled) {
            PublishArticleToWordPress::dispatch($publication->id)->onQueue('publishing')->afterCommit();
        }

        return redirect()->route('publications.show', $publication)->with(
            'success',
            $isScheduled ? 'Haber yayın takvimine eklendi.' : 'Haber WordPress yayın kuyruğuna gönderildi.',
        );
    }

    public function show(Publication $publication): View
    {
        Gate::authorize('view', $publication);

        return view('publications.show', ['publication' => $publication->load(['agency', 'article', 'publishingTarget', 'creator'])]);
    }

    /** @return array{agencies: Collection<int, Agency>, articles: Collection<int, Article>, targets: Collection<int, PublishingTarget>, remoteStatuses: array<int, RemotePublicationStatus>} */
    private function formOptions(User $user): array
    {
        return [
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get(),
            'articles' => Article::query()->visibleTo($user)->where('status', ArticleStatus::Published)->where('source_trust_status', SourceTrustStatus::Verified)->whereHas('seoAnalysis')->whereHas('selectedVisualAsset', fn ($query) => $query->where('status', VisualAssetStatus::Approved)->whereNotNull('storage_path'))->with('agency')->orderByDesc('created_at')->limit(200)->get(),
            'targets' => PublishingTarget::query()->visibleTo($user)->where('is_active', true)->with('agency')->orderBy('name')->get(),
            'remoteStatuses' => RemotePublicationStatus::cases(),
        ];
    }
}
