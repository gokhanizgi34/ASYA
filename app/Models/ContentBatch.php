<?php

namespace App\Models;

use App\ContentBatchStatus;
use Database\Factories\ContentBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agency_id', 'created_by', 'ai_prompt_id', 'name', 'status', 'total_items', 'processed_items', 'failed_items', 'settings', 'failure_message', 'started_at', 'completed_at'])]
class ContentBatch extends Model
{
    /** @use HasFactory<ContentBatchFactory> */
    use HasFactory;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<AiPrompt, $this> */
    public function aiPrompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class)->withTrashed();
    }

    /** @return HasMany<ContentBatchItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ContentBatchItem::class);
    }

    /**
     * @param  Builder<ContentBatch>  $query
     * @return Builder<ContentBatch>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSystemAdministrator() ? $query : $query->where('agency_id', $user->agency_id);
    }

    public function progressPercentage(): int
    {
        if ($this->total_items === 0) {
            return 0;
        }

        return (int) round((($this->processed_items + $this->failed_items) / $this->total_items) * 100);
    }

    public function canBeDispatched(): bool
    {
        return in_array($this->status, [ContentBatchStatus::Queued, ContentBatchStatus::Partial, ContentBatchStatus::Failed], true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ContentBatchStatus::class,
            'total_items' => 'integer',
            'processed_items' => 'integer',
            'failed_items' => 'integer',
            'settings' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
