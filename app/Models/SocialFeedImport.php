<?php

namespace App\Models;

use App\SocialFeedImportStatus;
use Database\Factories\SocialFeedImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'social_feed_source_id', 'started_by', 'status', 'received_count', 'imported_count', 'skipped_count', 'failed_count', 'errors', 'started_at', 'completed_at'])]
class SocialFeedImport extends Model
{
    /** @use HasFactory<SocialFeedImportFactory> */
    use HasFactory;

    public function source(): BelongsTo
    {
        return $this->belongsTo(SocialFeedSource::class, 'social_feed_source_id');
    }

    /** @param Builder<SocialFeedImport> $query */
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
            'status' => SocialFeedImportStatus::class,
            'received_count' => 'integer',
            'imported_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'errors' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
