<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeArticleSeoRequest;
use App\Models\Article;
use App\Models\SeoAnalysis;
use App\Services\SeoAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SeoAnalysisController extends Controller
{
    public function __construct(public SeoAnalyzer $analyzer) {}

    public function show(Article $article): View
    {
        Gate::authorize('view', $article);

        return view('seo.show', [
            'article' => $article->load(['agency', 'seoAnalysis']),
            'analysis' => $article->seoAnalysis,
        ]);
    }

    public function analyze(AnalyzeArticleSeoRequest $request, Article $article): RedirectResponse
    {
        $analysisData = $this->analyzer->analyze($article, $request->validated('focus_keyword'));

        SeoAnalysis::query()->updateOrCreate(
            ['article_id' => $article->id],
            ['agency_id' => $article->agency_id, ...$analysisData],
        );

        return redirect()->route('seo.show', $article)->with('success', 'SEO analizi tamamlandı.');
    }
}
