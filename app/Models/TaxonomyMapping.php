<?php

namespace App\Models;

use App\TaxonomyType;
use Database\Factories\TaxonomyMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'publishing_target_id', 'type', 'source_term', 'source_key', 'remote_id', 'remote_name', 'priority', 'is_active'])]
class TaxonomyMapping extends Model
{
    /** @use HasFactory<TaxonomyMappingFactory> */
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

    /** @param Builder<TaxonomyMapping> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSystemAdministrator()) {
            $query->where('agency_id', $user->agency_id);
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => TaxonomyType::class, 'remote_id' => 'integer', 'priority' => 'integer', 'is_active' => 'boolean'];
    }
}
