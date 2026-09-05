<?php

namespace App\Models;

use Database\Factories\EditorialStyleProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'created_by', 'name', 'sample_text', 'learned_terms', 'replacements', 'forbidden_terms', 'daily_quota', 'destination', 'is_active'])]
class EditorialStyleProfile extends Model
{
    /** @use HasFactory<EditorialStyleProfileFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<EditorialStyleProfile> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSystemAdministrator() ? $query : $query->where('agency_id', $user->agency_id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['learned_terms' => 'array', 'replacements' => 'array', 'forbidden_terms' => 'array', 'daily_quota' => 'integer', 'is_active' => 'boolean'];
    }
}
