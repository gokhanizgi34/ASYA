<?php

namespace App\Models;

use App\RawNewsStatus;
use Database\Factories\RawNewsItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['agency_id', 'news_source_id', 'external_id', 'source_name', 'source_url', 'original_title', 'original_body', 'original_image_url', 'language', 'status', 'priority', 'checksum', 'discovered_at', 'expires_at', 'processed_at', 'failure_message'])]
class RawNewsItem extends Model
{
    /** @use HasFactory<RawNewsItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function newsSource(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class);
    }

    /**
     * @param  Builder<RawNewsItem>  $query
     * @return Builder<RawNewsItem>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSystemAdministrator() ? $query : $query->where('agency_id', $user->agency_id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RawNewsStatus::class,
            'discovered_at' => 'datetime',
            'expires_at' => 'datetime',
            'processed_at' => 'datetime',
            'priority' => 'integer',
        ];
    }
}
