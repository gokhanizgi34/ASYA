<?php

namespace App\Http\Controllers;

use App\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ArticleBulkActionController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Article::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*' => ['required', 'integer', 'distinct', Rule::exists('articles', 'id')->whereNull('deleted_at')],
            'action' => ['required', Rule::in(['draft', 'pending_approval'])],
        ]);
        $ids = array_map('intval', $data['items']);
        $articles = Article::query()->visibleTo($user)->whereKey($ids)->get();
        abort_unless($articles->count() === count($ids), 403);
        $articles->each(fn (Article $article) => Gate::authorize('update', $article));
        $status = $data['action'] === 'draft' ? ArticleStatus::Draft : ArticleStatus::PendingApproval;
        $updated = Article::query()->whereKey($ids)->update([
            'status' => $status,
            'published_at' => null,
            'updated_at' => now(),
        ]);

        return back()->with('success', $updated.' haber toplu olarak güncellendi.');
    }
}
