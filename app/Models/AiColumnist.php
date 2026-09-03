<?php

namespace App\Models;

use Database\Factories\AiColumnistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agency_id', 'ai_prompt_id', 'created_by', 'name', 'slug', 'pen_name', 'biography', 'expertise', 'voice_guide', 'disclosure', 'is_active'])]
class AiColumnist extends Model
{
    /** @use HasFactory<AiColumnistFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function aiPrompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(ColumnistDraft::class);
    }

    /** @param Builder<AiColumnist> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSystemAdministrator()) {
            $query->where('agency_id', $user->agency_id);
        }
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['expertise' => 'array', 'is_active' => 'boolean'];
    }
}
