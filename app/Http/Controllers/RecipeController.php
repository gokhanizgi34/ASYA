<?php

namespace App\Http\Controllers;

use App\ArticleStatus;
use App\CopyrightStatus;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Recipe;
use App\Models\SeoAnalysis;
use App\Models\User;
use App\Models\VisualAsset;
use App\SourceTrustStatus;
use App\VisualAssetStatus;
use App\VisualSourceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Services\RecipeAiGenerator;

class RecipeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Recipe::class);

        return view('recipes.index', [
            'recipes' => Recipe::query()->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))->orderBy('category')->orderBy('title')->paginate(24)->withQueryString(),
            'categories' => ['main' => 'Ana yemek', 'cold' => 'Soğuk yemek', 'salad' => 'Salata', 'dessert' => 'Tatlı'],
            'agencies' => Agency::query()->where('is_active', true)->when(! $request->user()->isSystemAdministrator(), fn ($query) => $query->whereKey($request->user()->agency_id))->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Recipe::class);

        return view('recipes.create');
    }

    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        Recipe::query()->create($request->validated());

        return redirect()->route('recipes.index')->with('success', 'Tarif havuzuna eklendi.');
    }

    public function generate(Request $request, RecipeAiGenerator $generator): RedirectResponse
    {
        Gate::authorize('create', Recipe::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $agency = $user->isSystemAdministrator() ? Agency::query()->findOrFail($request->integer('agency_id')) : $user->agency;
        abort_unless($agency instanceof Agency, 422, 'Tarif üretimi için aktif ajans bulunamadı.');

        try {
            $recipes = $generator->generate($agency->id, max(1, (int) $agency->recipe_daily_quota));
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['recipe_generation' => $exception->getMessage()]);
        }

        return back()->with('success', count($recipes).' tarif AI ile oluşturuldu.');
    }

    public function show(Recipe $recipe): View
    {
        Gate::authorize('view', $recipe);

        return view('recipes.show', compact('recipe'));
    }

    public function edit(Recipe $recipe): View
    {
        Gate::authorize('update', $recipe);

        return view('recipes.edit', compact('recipe'));
    }

    public function update(UpdateRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $recipe->update($request->validated());

        return redirect()->route('recipes.index')->with('success', 'Tarif güncellendi.');
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        Gate::authorize('delete', $recipe);
        $recipe->delete();

        return redirect()->route('recipes.index')->with('success', 'Tarif silindi.');
    }

    public function publish(Request $request, Recipe $recipe): RedirectResponse
    {
        Gate::authorize('view', $recipe);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $agency = $user->isSystemAdministrator() ? Agency::query()->findOrFail($request->integer('agency_id')) : $user->agency;
        abort_unless($agency instanceof Agency, 422, 'Tarif için aktif ajans bulunamadı.');

        $title = $recipe->title.' Tarifi: Malzemeler ve Yapılışı';
        $article = Article::query()->create([
            'agency_id' => $agency->id,
            'author_id' => $user->id,
            'title' => $title,
            'slug' => Str::slug($recipe->title).'-tarif-'.now()->format('YmdHis'),
            'summary' => $recipe->title.' tarifi için malzemeler ve adım adım yapılış bilgileri.',
            'body' => "## Malzemeler\n\n{$recipe->ingredients}\n\n## Yapılışı\n\n{$recipe->instructions}",
            'status' => ArticleStatus::Published,
            'source_trust_status' => SourceTrustStatus::Verified,
            'source_name' => 'ASYA Tarif Havuzu',
            'editorial_metadata' => ['content_type' => 'recipe', 'recipe_id' => $recipe->id, 'category' => 'Yemek Tarifleri'],
            'published_at' => now(),
        ]);

        SeoAnalysis::query()->create([
            'agency_id' => $agency->id,
            'article_id' => $article->id,
            'focus_keyword' => $recipe->title.' tarifi',
            'meta_title' => $title,
            'meta_description' => $article->summary,
            'keywords' => [$recipe->title.' tarifi', 'pratik yemek tarifleri', 'malzemeler ve yapılışı'],
            'hashtags' => ['#YemekTarifi', '#PratikTarif'],
            'score' => 100,
            'readability_score' => 100,
            'word_count' => str_word_count($article->body),
            'keyword_density' => 0,
            'issues' => [],
            'recommendations' => [],
            'analyzed_at' => now(),
        ]);

        if ($agency->logo_path && Storage::disk('public')->exists($agency->logo_path)) {
            $bytes = Storage::disk('public')->get($agency->logo_path);
            $info = @getimagesizefromstring($bytes);
            if (is_array($info)) {
                $extension = match ($info['mime'] ?? '') {
                    'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg'
                };
                $path = 'visual-assets/recipes/'.$agency->id.'/'.$article->id.'-logo.'.$extension;
                Storage::disk('public')->put($path, $bytes);
                VisualAsset::query()->create(['agency_id' => $agency->id, 'article_id' => $article->id, 'uploaded_by' => $user->id, 'title' => $title, 'source_type' => VisualSourceType::Upload, 'status' => VisualAssetStatus::Approved, 'copyright_status' => CopyrightStatus::Original, 'storage_disk' => 'public', 'storage_path' => $path, 'mime_type' => $info['mime'] ?? null, 'width' => $info[0], 'height' => $info[1], 'quality_score' => 100, 'alt_text' => $title, 'is_selected' => true, 'evaluated_at' => now()]);
            }
        }

        return redirect()->route('publications.create', ['article_id' => $article->id])->with('success', 'Tarif haber olarak yayın merkezine gönderildi.');
    }
}
