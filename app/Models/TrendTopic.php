<?php

namespace App\Models;

use App\TrendStatus;
use Database\Factories\TrendTopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agency_id', 'name', 'normalized_name', 'status', 'mention_count', 'source_count', 'score', 'velocity', 'context', 'first_seen_at', 'last_seen_at', 'analyzed_at'])]
class TrendTopic extends Model
{
    /** @use HasFactory<TrendTopicFactory> */
    use HasFactory;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<TrendSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(TrendSnapshot::class);
    }

    /** @param Builder<TrendTopic> $query @return Builder<TrendTopic> */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSystemAdministrator() ? $query : $query->where('agency_id', $user->agency_id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => TrendStatus::class, 'mention_count' => 'integer', 'source_count' => 'integer', 'score' => 'float', 'velocity' => 'float', 'context' => 'array', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime', 'analyzed_at' => 'datetime'];
    }
}
