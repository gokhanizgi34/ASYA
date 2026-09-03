<?php

namespace App\Models;

use App\PublishingProtocol;
use Database\Factories\PublishingTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['agency_id', 'name', 'base_url', 'protocol', 'username', 'credential', 'default_author_id', 'default_category_ids', 'default_tag_ids', 'is_active', 'last_connected_at', 'last_error'])]
#[Hidden(['credential'])]
class PublishingTarget extends Model
{
    /** @use HasFactory<PublishingTargetFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return HasMany<Publication, $this> */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    /**
     * @param  Builder<PublishingTarget>  $query
     * @return Builder<PublishingTarget>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSystemAdministrator() ? $query : $query->where('agency_id', $user->agency_id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'protocol' => PublishingProtocol::class,
            'credential' => 'encrypted',
            'default_author_id' => 'integer',
            'default_category_ids' => 'array',
            'default_tag_ids' => 'array',
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
        ];
    }
}
