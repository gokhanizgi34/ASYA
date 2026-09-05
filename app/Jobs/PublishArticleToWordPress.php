<?php

namespace App\Jobs;

use App\Models\Publication;
use App\PublicationStatus;
use App\Services\NotificationCenter;
use App\Services\WordPressPublisher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublishArticleToWordPress implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [300, 300];

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public int $publicationId) {}

    public function uniqueId(): string
    {
        return (string) $this->publicationId;
    }

    public function handle(WordPressPublisher $publisher, ?NotificationCenter $notifications = null): void
    {
        $publication = DB::transaction(function (): ?Publication {
            $locked = Publication::query()->with(['publishingTarget', 'article'])->lockForUpdate()->find($this->publicationId);
            if (! $locked || $locked->status === PublicationStatus::Published || ! $locked->canBeDispatched()) {
                return null;
            }

            $locked->forceFill([
                'status' => PublicationStatus::Publishing,
                'attempt_count' => $locked->attempt_count + 1,
                'started_at' => now(),
                'completed_at' => null,
                'failure_message' => null,
            ])->save();

            return $locked;
        }, 3);

        if (! $publication) {
            return;
        }

        try {
            $result = $publisher->publish($publication);
            $publication->forceFill([
                'status' => PublicationStatus::Published,
                'remote_post_id' => $result['post_id'],
                'remote_media_id' => $result['media_id'],
                'remote_url' => $result['url'],
                'response_meta' => $result['response_meta'],
                'published_at' => now(),
                'completed_at' => now(),
                'failure_message' => null,
            ])->save();
            $publication->publishingTarget->forceFill(['last_connected_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $exception) {
            $message = str($exception->getMessage())->limit(1000)->toString();
            $publication->forceFill(['status' => PublicationStatus::Failed, 'failure_message' => $message, 'completed_at' => now()])->save();
            $publication->publishingTarget->forceFill(['last_error' => $message])->save();
            ($notifications ?? app(NotificationCenter::class))->publicationFailed($publication);
            report($exception);

            if (str_contains($message, 'WordPress kullanıcısı') && str_contains($message, 'yetkili değil')) {
                return;
            }

            throw $exception;
        }
    }
}
