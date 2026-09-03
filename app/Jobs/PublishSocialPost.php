<?php

namespace App\Jobs;

use App\Models\SocialPost;
use App\Services\SocialPublisher;
use App\SocialPostStatus;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PublishSocialPost implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [300, 300];

    public int $uniqueFor = 3600;

    public function __construct(public int $socialPostId) {}

    public function uniqueId(): string
    {
        return (string) $this->socialPostId;
    }

    public function handle(SocialPublisher $publisher): void
    {
        $post = SocialPost::findOrFail($this->socialPostId);

        if ($post->status === SocialPostStatus::Published) {
            return;
        }

        try {
            $externalId = $publisher->publish($post);
            $post->update([
                'status' => SocialPostStatus::Published,
                'external_id' => $externalId,
                'error_message' => null,
                'published_at' => now(),
            ]);
            $post->account()->update(['last_published_at' => now()]);
        } catch (Throwable $exception) {
            $post->update([
                'status' => SocialPostStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
