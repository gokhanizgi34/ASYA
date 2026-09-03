<?php

namespace App\Models;

use Database\Factories\SocialListeningWatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agency_id', 'created_by', 'name', 'keywords', 'excluded_terms', 'platforms', 'alert_threshold', 'is_active'])]
class SocialListeningWatch extends Model
{
    /** @use HasFactory<SocialListeningWatchFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(SocialMention::class);
    }

    /** @param Builder<SocialListeningWatch> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSystemAdministrator()) {
            $query->where('agency_id', $user->agency_id);
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'excluded_terms' => 'array',
            'platforms' => 'array',
            'alert_threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
