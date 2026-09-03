<?php

namespace App\Http\Controllers;

use App\ArticleStatus;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Models\Agency;
use App\Models\Article;
use App\Models\User;
use App\SourceTrustStatus;
use App\UserRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Article::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $query = Article::query()->visibleTo($user)->with(['agency', 'author', 'seoAnalysis', 'selectedVisualAsset']);
        $status = (string) $request->query('status', '');
        $trustStatus = (string) $request->query('trust', '');
        $search = trim((string) $request->query('q', ''));

        if (ArticleStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        if (SourceTrustStatus::tryFrom($trustStatus)) {
            $query->where('source_trust_status', $trustStatus);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('source_name', 'like', "%{$search}%");
            });
        }

        $statusCounts = collect(ArticleStatus::cases())->mapWithKeys(fn (ArticleStatus $item): array => [
            $item->value => Article::query()->visibleTo($user)->where('status', $item)->count(),
        ]);

        return view('articles.index', [
            'articles' => $query->latest()->paginate(15)->withQueryString(),
            'statuses' => ArticleStatus::cases(),
            'trustStatuses' => SourceTrustStatus::cases(),
            'statusCounts' => $statusCounts,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Article::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('articles.create', $this->formOptions($user));
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['author_id'] = $request->user()->id;
        $data['published_at'] = $data['status'] === ArticleStatus::Published->value ? now() : null;
        $article = Article::query()->create($data);
        $this->forgetDashboardMetrics($article->agency_id);

        return redirect()->route('articles.show', $article)->with('success', 'Haber başarıyla oluşturuldu.');
    }

    public function show(Article $article): View
    {
        Gate::authorize('view', $article);

        return view('articles.show', ['article' => $article->load(['agency', 'author', 'seoAnalysis', 'selectedVisualAsset'])]);
    }

    public function edit(Request $request, Article $article): View
    {
        Gate::authorize('update', $article);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('articles.edit', [
            'article' => $article,
            ...$this->formOptions($user, $article),
        ]);
    }

    public function update(UpdateArticleRequest $request, Article $article): RedirectResponse
    {
        $data = $request->validated();
        $data['published_at'] = $data['status'] === ArticleStatus::Published->value
            ? ($article->published_at ?? now())
            : null;
        $article->update($data);
        $this->forgetDashboardMetrics($article->agency_id);

        return redirect()->route('articles.show', $article)->with('success', 'Haber güncellendi.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        Gate::authorize('delete', $article);
        $agencyId = $article->agency_id;
        $article->delete();
        $this->forgetDashboardMetrics($agencyId);

        return redirect()->route('articles.index')->with('success', 'Haber geri alınabilir şekilde silindi.');
    }

    /**
     * @return array{agencies: Collection<int, Agency>, statuses: array<int, ArticleStatus>, trustStatuses: array<int, SourceTrustStatus>}
     */
    private function formOptions(User $user, ?Article $article = null): array
    {
        $agencies = Agency::query()
            ->where(function ($query) use ($article): void {
                $query->where('is_active', true);

                if ($article?->agency_id) {
                    $query->orWhereKey($article->agency_id);
                }
            })
            ->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))
            ->orderBy('name')
            ->get();

        $statuses = match ($user->role) {
            UserRole::SystemAdministrator => ArticleStatus::cases(),
            UserRole::AgencyOwner => [ArticleStatus::Draft, ArticleStatus::PendingApproval, ArticleStatus::Published],
            UserRole::Editor => [ArticleStatus::Draft, ArticleStatus::PendingApproval],
        };

        return [
            'agencies' => $agencies,
            'statuses' => $statuses,
            'trustStatuses' => SourceTrustStatus::cases(),
        ];
    }

    private function forgetDashboardMetrics(int $agencyId): void
    {
        Cache::forget('dashboard.metrics.'.UserRole::SystemAdministrator->value.'.global');
        Cache::forget('dashboard.metrics.'.UserRole::AgencyOwner->value.'.'.$agencyId);
        Cache::forget('dashboard.metrics.'.UserRole::Editor->value.'.'.$agencyId);
    }
}
