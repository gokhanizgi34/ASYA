<?php

namespace App\Models;

use App\SourceTrustBand;
use Database\Factories\NewsSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agency_id', 'created_by', 'name', 'domain', 'feed_url', 'feed_url_hash', 'allow_insecure_tls', 'feed_format', 'source_type', 'notes', 'is_active', 'daily_item_limit', 'latest_score', 'latest_band', 'last_assessed_at', 'last_fetched_at', 'last_status_code', 'last_ingestion_method', 'last_content_fingerprint', 'last_change_detected_at', 'last_crawled_pages'])]
class NewsSource extends Model
{
    /** @use HasFactory<NewsSourceFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(SourceTrustAssessment::class);
    }

    public function rawNewsItems(): HasMany
    {
        return $this->hasMany(RawNewsItem::class);
    }

    /** @param Builder<NewsSource> $query */
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
            'is_active' => 'boolean',
            'allow_insecure_tls' => 'boolean',
            'daily_item_limit' => 'integer',
            'latest_score' => 'float',
            'latest_band' => SourceTrustBand::class,
            'last_assessed_at' => 'datetime',
            'last_fetched_at' => 'datetime',
            'last_status_code' => 'integer',
            'last_item_count' => 'integer',
            'last_change_detected_at' => 'datetime',
            'last_crawled_pages' => 'integer',
        ];
    }
}
