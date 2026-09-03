<?php

namespace App\Models;

use Database\Factories\TrendSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trend_topic_id', 'mention_count', 'source_count', 'score', 'velocity', 'period_start', 'period_end'])]
class TrendSnapshot extends Model
{
    /** @use HasFactory<TrendSnapshotFactory> */
    use HasFactory;

    /** @return BelongsTo<TrendTopic, $this> */
    public function trendTopic(): BelongsTo
    {
        return $this->belongsTo(TrendTopic::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['mention_count' => 'integer', 'source_count' => 'integer', 'score' => 'float', 'velocity' => 'float', 'period_start' => 'datetime', 'period_end' => 'datetime'];
    }
}
