<?php

namespace App\Services;

use App\Jobs\PublishSocialPost;
use App\Models\Article;
use App\Models\SocialPost;
use App\Models\SocialPublishingAccount;
use App\PublicationStatus;
use App\SocialPostStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AutomaticSocialPublisher
{
    public function __construct(private readonly XPostComposer $xPostComposer) {}

    public function publish(Article $article): void
    {
        $article->loadMissing('author', 'selectedVisualAsset', 'publications.publishingTarget');
        $accounts = SocialPublishingAccount::query()
            ->where('agency_id', $article->agency_id)
            ->where('platform', 'x')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
        $linkUrl = $this->publishedLink($article);

        if ($linkUrl === null) {
            return;
        }

        foreach ($accounts as $account) {
            [$post, $shouldDispatch] = DB::transaction(function () use ($account, $article, $linkUrl): array {
                $existing = SocialPost::query()
                    ->where('social_publishing_account_id', $account->id)
                    ->where('article_id', $article->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->status === SocialPostStatus::Failed) {
                        $existing->forceFill([
                            'content' => $this->xPostComposer->compose($article, $account),
                            'link_url' => $linkUrl,
                            'status' => SocialPostStatus::Queued,
                            'error_message' => null,
                        ])->save();
                    }

                    return [$existing, $existing->wasChanged('status')];
                }

                $asset = $article->selectedVisualAsset;

                $post = SocialPost::query()->create([
                    'agency_id' => $article->agency_id,
                    'social_publishing_account_id' => $account->id,
                    'article_id' => $article->id,
                    'created_by' => $article->author_id,
                    'content' => $this->xPostComposer->compose($article, $account),
                    'link_url' => $linkUrl,
                    'media_url' => $asset?->storage_path && $asset->storage_disk
                        ? Storage::disk($asset->storage_disk)->url($asset->storage_path)
                        : null,
                    'status' => SocialPostStatus::Queued,
                ]);

                return [$post, true];
            }, 3);

            if ($shouldDispatch && $post->status === SocialPostStatus::Queued) {
                PublishSocialPost::dispatch($post->id)->onQueue('publishing')->afterCommit();
            }
        }
    }

    private function publishedLink(Article $article): ?string
    {
        $publication = $article->publications
            ->where('status', PublicationStatus::Published)
            ->sortByDesc('published_at')
            ->first();

        if (! $publication) {
            return null;
        }

        if (filled($publication->remote_url)) {
            return (string) $publication->remote_url;
        }

        $baseUrl = $publication->publishingTarget?->base_url;

        return filled($baseUrl) ? rtrim((string) $baseUrl, '/').'/'.$article->slug.'/' : null;
    }
}
