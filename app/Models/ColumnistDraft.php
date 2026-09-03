<?php

namespace App\Models;

use App\ColumnistDraftStatus;
use Database\Factories\ColumnistDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'ai_columnist_id', 'created_by', 'reviewed_by', 'topic', 'source_notes', 'headline', 'body', 'prompt_snapshot', 'status', 'reviewed_at'])]
class ColumnistDraft extends Model
{
    /** @use HasFactory<ColumnistDraftFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function columnist(): BelongsTo
    {
        return $this->belongsTo(AiColumnist::class, 'ai_columnist_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @param Builder<ColumnistDraft> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSystemAdministrator()) {
            $query->where('agency_id', $user->agency_id);
        }
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['source_notes' => 'encrypted', 'prompt_snapshot' => 'array', 'status' => ColumnistDraftStatus::class, 'reviewed_at' => 'datetime'];
    }
}
