<?php

namespace App\Http\Controllers;

use App\ArticleStatus;
use App\Http\Requests\StoreMultiSiteDistributionRequest;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\User;
use App\RemotePublicationStatus;
use App\Services\PublicationCreator;
use App\SourceTrustStatus;
use App\VisualAssetStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MultiSiteDistributionController extends Controller
{
    public function create(Request $request): View
    {
        Gate::authorize('create', Publication::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('publications.multi-site', [
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get(),
            'articles' => Article::query()->visibleTo($user)->where('status', ArticleStatus::Published)->where('source_trust_status', SourceTrustStatus::Verified)->whereHas('seoAnalysis')->whereHas('selectedVisualAsset', fn ($query) => $query->where('status', VisualAssetStatus::Approved)->whereNotNull('storage_path'))->with('agency')->latest()->limit(200)->get(),
            'targets' => PublishingTarget::query()->visibleTo($user)->where('is_active', true)->with('agency')->orderBy('name')->get(),
            'remoteStatuses' => RemotePublicationStatus::cases(),
        ]);
    }

    public function store(StoreMultiSiteDistributionRequest $request, PublicationCreator $creator): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validated();

        $publications = DB::transaction(function () use ($data, $user, $creator): array {
            return collect($data['publishing_target_ids'])
                ->map(fn (int $targetId): Publication => $creator->create([...$data, 'publishing_target_id' => $targetId], $user))
                ->all();
        }, 3);

        if (blank($data['scheduled_for'] ?? null)) {
            foreach ($publications as $publication) {
                PublishArticleToWordPress::dispatch($publication->id)->onQueue('publishing')->afterCommit();
            }
        }

        return redirect()->route('publications.index')->with(
            'success',
            count($publications).' site için yayın '.(filled($data['scheduled_for'] ?? null) ? 'planlandı.' : 'kuyruğa alındı.'),
        );
    }
}
