<?php

namespace App\Models;

use App\ContentBatchItemStatus;
use Database\Factories\ContentBatchItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['content_batch_id', 'raw_news_item_id', 'article_id', 'status', 'failure_message', 'started_at', 'completed_at'])]
class ContentBatchItem extends Model
{
    /** @use HasFactory<ContentBatchItemFactory> */
    use HasFactory;

    /** @return BelongsTo<ContentBatch, $this> */
    public function contentBatch(): BelongsTo
    {
        return $this->belongsTo(ContentBatch::class);
    }

    /** @return BelongsTo<RawNewsItem, $this> */
    public function rawNewsItem(): BelongsTo
    {
        return $this->belongsTo(RawNewsItem::class)->withTrashed();
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class)->withTrashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ContentBatchItemStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
