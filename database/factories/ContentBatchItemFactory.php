<?php

namespace Database\Factories;

use App\ContentBatchItemStatus;
use App\Models\ContentBatch;
use App\Models\ContentBatchItem;
use App\Models\RawNewsItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentBatchItem> */
class ContentBatchItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'content_batch_id' => ContentBatch::factory(),
            'raw_news_item_id' => RawNewsItem::factory(),
            'article_id' => null,
            'status' => ContentBatchItemStatus::Queued,
            'failure_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
