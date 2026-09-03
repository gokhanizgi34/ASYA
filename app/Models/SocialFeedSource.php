<?php

namespace App\Models;

use Database\Factories\SocialFeedSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agency_id', 'social_listening_watch_id', 'created_by', 'name', 'platform', 'source_type', 'endpoint_url', 'auth_secret', 'field_map', 'is_active', 'last_imported_at'])]
class SocialFeedSource extends Model
{
    /** @use HasFactory<SocialFeedSourceFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function watch(): BelongsTo
    {
        return $this->belongsTo(SocialListeningWatch::class, 'social_listening_watch_id');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(SocialFeedImport::class);
    }

    /** @param Builder<SocialFeedSource> $query */
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
            'auth_secret' => 'encrypted',
            'field_map' => 'array',
            'is_active' => 'boolean',
            'last_imported_at' => 'datetime',
        ];
    }
}
