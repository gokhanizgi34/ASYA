<?php

namespace App\Http\Controllers;

use App\ArticleStatus;
use App\Jobs\FinalizeAutomaticArticle;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ArticleRetryController extends Controller
{
    public function __invoke(Article $article): RedirectResponse
    {
        Gate::authorize('update', $article);
        abort_unless($article->status === ArticleStatus::Failed, 422, 'Yalnızca hatalı haber yeniden denenebilir.');

        $article->forceFill(['status' => ArticleStatus::Draft, 'failure_message' => null])->save();
        FinalizeAutomaticArticle::dispatch($article->id)->onQueue('content')->afterCommit();

        return back()->with('success', 'Haber yeniden işleme kuyruğuna alındı.');
    }
}
