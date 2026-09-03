<?php

namespace App\Models;

use App\CampaignStatus;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['agency_id', 'owner_id', 'name', 'status', 'objective', 'target_audience', 'channels', 'brief', 'kpis', 'budget', 'starts_at', 'ends_at'])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<CampaignContent, $this> */
    public function contents(): HasMany
    {
        return $this->hasMany(CampaignContent::class);
    }

    /** @param Builder<Campaign> $query @return Builder<Campaign> */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSystemAdministrator() ? $query : $query->where('agency_id', $user->agency_id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => CampaignStatus::class, 'channels' => 'array', 'kpis' => 'array', 'budget' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
