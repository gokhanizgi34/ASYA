<?php

namespace App\Services;

use App\ArticleStatus;
use App\ContentBatchItemStatus;
use App\ContentBatchStatus;
use App\Jobs\FinalizeAutomaticArticle;
use App\Models\Article;
use App\Models\ContentBatch;
use App\Models\ContentBatchItem;
use App\Models\RawNewsItem;
use App\RawNewsStatus;
use App\SourceTrustStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ContentBatchProcessor
{
    public function __construct(private ContentTransformer $transformer) {}

    public function process(int $contentBatchId): void
    {
        $batch = DB::transaction(function () use ($contentBatchId): ?ContentBatch {
            $batch = ContentBatch::query()->lockForUpdate()->find($contentBatchId);

            if (! $batch || $batch->status === ContentBatchStatus::Completed) {
                return null;
            }

            $batch->update([
                'status' => ContentBatchStatus::Processing,
                'started_at' => $batch->started_at ?? now(),
                'completed_at' => null,
                'failure_message' => null,
            ]);

            return $batch;
        }, 3);

        if (! $batch) {
            return;
        }

        $promptSnapshot = $batch->settings['prompt_snapshot'] ?? [];

        $batch->items()
            ->whereIn('status', [ContentBatchItemStatus::Queued, ContentBatchItemStatus::Failed])
            ->lazyById(50)
            ->each(fn (ContentBatchItem $item) => $this->processItem($item->id, $batch, $promptSnapshot));

        $this->refreshBatchStatus($batch->id);
    }

    /** @param array<string, mixed> $promptSnapshot */
    private function processItem(int $contentBatchItemId, ContentBatch $batch, array $promptSnapshot): void
    {
        try {
            $rawNewsItemId = $this->claimItem($contentBatchItemId);

            if (! $rawNewsItemId) {
                return;
            }

            $rawNewsItem = RawNewsItem::query()->findOrFail($rawNewsItemId);
            $isAutomatic = (bool) data_get($batch->settings, 'automatic_pipeline', false);
            $content = $this->transformer->transform($rawNewsItem, $promptSnapshot, $isAutomatic);
            $articleId = $this->storeArticle($contentBatchItemId, $batch, $content);

            if ($isAutomatic && $articleId) {
                FinalizeAutomaticArticle::dispatch($articleId)
                    ->onQueue('content')
                    ->afterCommit();
            }
        } catch (Throwable $exception) {
            $this->markItemFailed($contentBatchItemId, $exception);

            Log::error('İçerik Fabrikası öğesi işlenemedi.', [
                'content_batch_id' => $batch->id,
                'content_batch_item_id' => $contentBatchItemId,
                'exception' => $exception,
            ]);
        }
    }

    private function claimItem(int $contentBatchItemId): ?int
    {
        return DB::transaction(function () use ($contentBatchItemId): ?int {
            $item = ContentBatchItem::query()->lockForUpdate()->findOrFail($contentBatchItemId);

            if (in_array($item->status, [ContentBatchItemStatus::Completed, ContentBatchItemStatus::Skipped], true)) {
                return null;
            }

            $rawNewsItem = RawNewsItem::query()->lockForUpdate()->findOrFail($item->raw_news_item_id);

            if ($rawNewsItem->status === RawNewsStatus::Processed) {
                $item->update([
                    'status' => ContentBatchItemStatus::Skipped,
                    'failure_message' => 'Ham haber daha önce başka bir üretim bandında işlendi.',
                    'completed_at' => now(),
                ]);

                return null;
            }

            $item->update([
                'status' => ContentBatchItemStatus::Processing,
                'started_at' => $item->started_at ?? now(),
                'completed_at' => null,
                'failure_message' => null,
            ]);
            $rawNewsItem->update(['status' => RawNewsStatus::Processing, 'failure_message' => null]);

            return $rawNewsItem->id;
        }, 3);
    }

    /**
     * @param  array{title: string, summary: string, body: string, focus_keyword?: string, keywords?: array<int, string>, hashtags?: array<int, string>, category?: string, ai_provider?: string}  $content
     */
    private function storeArticle(int $contentBatchItemId, ContentBatch $batch, array $content): ?int
    {
        return DB::transaction(function () use ($contentBatchItemId, $batch, $content): ?int {
            $item = ContentBatchItem::query()->lockForUpdate()->findOrFail($contentBatchItemId);

            if ($item->status === ContentBatchItemStatus::Completed) {
                return $item->article_id;
            }

            $rawNewsItem = RawNewsItem::query()->lockForUpdate()->findOrFail($item->raw_news_item_id);

            if ($rawNewsItem->status === RawNewsStatus::Processed) {
                $item->update([
                    'status' => ContentBatchItemStatus::Skipped,
                    'failure_message' => 'Ham haber eş zamanlı olarak başka bir üretim bandında işlendi.',
                    'completed_at' => now(),
                ]);

                return null;
            }

            $slugBase = Str::slug($content['title']) ?: 'haber';
            $article = Article::query()->create([
                'agency_id' => $batch->agency_id,
                'author_id' => $batch->created_by,
                'title' => $content['title'],
                'slug' => $slugBase.'-raw-'.$rawNewsItem->id,
                'summary' => $content['summary'],
                'body' => $content['body'],
                'editorial_metadata' => [
                    'focus_keyword' => $content['focus_keyword'] ?? null,
                    'keywords' => $content['keywords'] ?? [],
                    'hashtags' => $content['hashtags'] ?? [],
                    'category' => $content['category'] ?? 'Gündem',
                    'ai_provider' => $content['ai_provider'] ?? null,
                ],
                'status' => ArticleStatus::Draft,
                'source_trust_status' => SourceTrustStatus::Unverified,
                'source_name' => $rawNewsItem->source_name,
                'source_url' => $rawNewsItem->source_url,
                'published_at' => null,
                'failure_message' => null,
            ]);

            $rawNewsItem->update([
                'status' => RawNewsStatus::Processed,
                'processed_at' => now(),
                'failure_message' => null,
            ]);
            $item->update([
                'article_id' => $article->id,
                'status' => ContentBatchItemStatus::Completed,
                'completed_at' => now(),
            ]);

            return $article->id;
        }, 3);
    }

    private function markItemFailed(int $contentBatchItemId, Throwable $exception): void
    {
        DB::transaction(function () use ($contentBatchItemId, $exception): void {
            $item = ContentBatchItem::query()->lockForUpdate()->find($contentBatchItemId);

            if (! $item || in_array($item->status, [ContentBatchItemStatus::Completed, ContentBatchItemStatus::Skipped], true)) {
                return;
            }

            $message = Str::limit($exception->getMessage(), 1900);
            $item->update([
                'status' => ContentBatchItemStatus::Failed,
                'failure_message' => $message,
                'completed_at' => now(),
            ]);
            RawNewsItem::query()
                ->whereKey($item->raw_news_item_id)
                ->where('status', '!=', RawNewsStatus::Processed)
                ->update(['status' => RawNewsStatus::Failed, 'failure_message' => $message]);
        }, 3);
    }

    private function refreshBatchStatus(int $contentBatchId): void
    {
        DB::transaction(function () use ($contentBatchId): void {
            $batch = ContentBatch::query()->lockForUpdate()->findOrFail($contentBatchId);
            $processedItems = $batch->items()->whereIn('status', [ContentBatchItemStatus::Completed, ContentBatchItemStatus::Skipped])->count();
            $failedItems = $batch->items()->where('status', ContentBatchItemStatus::Failed)->count();
            $status = match (true) {
                $failedItems === 0 && $processedItems === $batch->total_items => ContentBatchStatus::Completed,
                $processedItems > 0 => ContentBatchStatus::Partial,
                default => ContentBatchStatus::Failed,
            };

            $batch->update([
                'status' => $status,
                'processed_items' => $processedItems,
                'failed_items' => $failedItems,
                'failure_message' => $failedItems > 0 ? 'Bazı ham haberler işlenemedi; ayrıntılar öğe kayıtlarında yer alıyor.' : null,
                'completed_at' => now(),
            ]);
        }, 3);
    }
}
