<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArticleTranslationRequest;
use App\Http\Requests\UpdateArticleTranslationRequest;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\User;
use App\Services\LocalTranslationDraftBuilder;
use App\TranslationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ArticleTranslationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ArticleTranslation::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('translations.index', [
            'translations' => ArticleTranslation::query()->visibleTo($user)->with(['article', 'reviewer'])->latest()->paginate(20),
            'articles' => Article::query()->visibleTo($user)->latest()->limit(100)->get(),
            'locales' => ['en' => 'İngilizce', 'de' => 'Almanca', 'fr' => 'Fransızca', 'es' => 'İspanyolca', 'ar' => 'Arapça', 'ru' => 'Rusça'],
        ]);
    }

    public function store(StoreArticleTranslationRequest $request, LocalTranslationDraftBuilder $builder): RedirectResponse
    {
        $article = Article::findOrFail($request->integer('article_id'));
        $targetLocale = $request->validated('target_locale');
        $draft = $builder->build($article, $targetLocale);

        $translation = ArticleTranslation::create([
            'agency_id' => $article->agency_id,
            'article_id' => $article->id,
            'created_by' => $request->user()?->id,
            'source_locale' => 'tr',
            'target_locale' => $targetLocale,
            ...$draft,
            'glossary' => [],
            'status' => TranslationStatus::Draft,
        ]);

        return redirect()->route('translations.show', $translation)->with('success', 'Yerel editoryal çeviri taslağı oluşturuldu.');
    }

    public function show(ArticleTranslation $articleTranslation): View
    {
        Gate::authorize('view', $articleTranslation);

        return view('translations.show', [
            'translation' => $articleTranslation->load(['article', 'reviewer']),
            'statuses' => TranslationStatus::cases(),
        ]);
    }

    public function update(UpdateArticleTranslationRequest $request, ArticleTranslation $articleTranslation): RedirectResponse
    {
        $status = TranslationStatus::from($request->validated('status'));
        $reviewed = in_array($status, [TranslationStatus::Approved, TranslationStatus::Rejected], true);

        $articleTranslation->update([
            ...$request->validated(),
            'reviewed_by' => $reviewed ? $request->user()?->id : null,
            'reviewed_at' => $reviewed ? now() : null,
        ]);

        return back()->with('success', 'Çeviri güncellendi.');
    }

    public function refresh(Request $request, ArticleTranslation $articleTranslation, LocalTranslationDraftBuilder $builder): RedirectResponse
    {
        Gate::authorize('update', $articleTranslation);
        $draft = $builder->build($articleTranslation->article, $articleTranslation->target_locale);

        $articleTranslation->update([
            ...$draft,
            'status' => TranslationStatus::Draft,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return back()->with('success', 'Taslak güncel kaynak haberden yeniden oluşturuldu.');
    }
}
