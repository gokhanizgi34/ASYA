<?php

namespace App\Models;

use App\HttpMethod;
use Database\Factories\LearnedRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'publishing_target_id', 'host', 'path_pattern', 'method', 'purpose', 'successful_count', 'failed_count', 'confidence', 'last_status_code', 'is_enabled', 'first_observed_at', 'last_observed_at', 'last_success_at'])]
class LearnedRoute extends Model
{
    /** @use HasFactory<LearnedRouteFactory> */
    use HasFactory;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<PublishingTarget, $this> */
    public function publishingTarget(): BelongsTo
    {
        return $this->belongsTo(PublishingTarget::class);
    }

    /** @param Builder<LearnedRoute> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSystemAdministrator()) {
            $query->where('agency_id', $user->agency_id);
        }
    }

    public function observationCount(): int
    {
        return $this->successful_count + $this->failed_count;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'confidence' => 'float',
            'is_enabled' => 'boolean',
            'first_observed_at' => 'datetime',
            'last_observed_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }
}
