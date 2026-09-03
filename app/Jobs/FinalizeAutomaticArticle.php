<?php

namespace App\Jobs;

use App\ArticleStatus;
use App\Models\Article;
use App\Services\AutomaticArticlePublisher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class FinalizeAutomaticArticle implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var array<int, int> */
    public array $backoff = [300, 300];

    public int $uniqueFor = 900;

    public function __construct(public int $articleId) {}

    public function handle(AutomaticArticlePublisher $publisher): void
    {
        $publisher->publish($this->articleId);
    }

    public function uniqueId(): string
    {
        return (string) $this->articleId;
    }

    public function failed(?Throwable $exception): void
    {
        Article::query()->whereKey($this->articleId)->update([
            'status' => ArticleStatus::Failed,
            'failure_message' => Str::limit($exception?->getMessage() ?? 'Otomatik haber yayın hattı tamamlanamadı.', 1900),
        ]);
    }
}
