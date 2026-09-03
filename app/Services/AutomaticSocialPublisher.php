<?php

namespace App\Services;

use App\Jobs\PublishSocialPost;
use App\Models\Article;
use App\Models\SocialPost;
use App\Models\SocialPublishingAccount;
use App\SocialPostStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AutomaticSocialPublisher
{
    public function publish(Article $article): void
    {
        $article->loadMissing('author', 'selectedVisualAsset');
        $accounts = SocialPublishingAccount::query()
            ->where('agency_id', $article->agency_id)
            ->whereIn('platform', ['facebook', 'instagram', 'x'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($accounts as $account) {
            $post = DB::transaction(function () use ($account, $article): SocialPost {
                $existing = SocialPost::query()
                    ->where('social_publishing_account_id', $account->id)
                    ->where('article_id', $article->id)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $asset = $article->selectedVisualAsset;

                return SocialPost::query()->create([
                    'agency_id' => $article->agency_id,
                    'social_publishing_account_id' => $account->id,
                    'article_id' => $article->id,
                    'created_by' => $article->author_id,
                    'content' => $article->title."\n\n".$article->summary,
                    'link_url' => url('/haberler/'.$article->slug),
                    'media_url' => $asset?->storage_path && $asset->storage_disk
                        ? Storage::disk($asset->storage_disk)->url($asset->storage_path)
                        : null,
                    'status' => SocialPostStatus::Queued,
                ]);
            }, 3);

            if ($post->status === SocialPostStatus::Queued) {
                PublishSocialPost::dispatch($post->id)->onQueue('publishing')->afterCommit();
            }
        }
    }
}
