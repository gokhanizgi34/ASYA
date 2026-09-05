<?php

namespace App\Models;

use App\PublicationStatus;
use App\RemotePublicationStatus;
use Database\Factories\PublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agency_id', 'article_id', 'publishing_target_id', 'created_by', 'status', 'remote_status', 'remote_post_id', 'remote_media_id', 'remote_url', 'payload', 'response_meta', 'attempt_count', 'failure_message', 'queued_at', 'started_at', 'published_at', 'completed_at'])]
class Publication extends Model
{
    /** @use HasFactory<PublicationFactory> */
    use HasFactory;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<PublishingTarget, $this> */
    public function publishingTarget(): BelongsTo
    {
        return $this->belongsTo(PublishingTarget::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Publication>  $query
     * @return Builder<Publication>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSystemAdministrator() ? $query : $query->where('agency_id', $user->agency_id);
    }

    public function canBeDispatched(): bool
    {
        return in_array($this->status, [PublicationStatus::Queued, PublicationStatus::Failed], true);
    }

    /** @return HasMany<ScheduleEntry, $this> */
    public function scheduleEntries(): HasMany
    {
        return $this->hasMany(ScheduleEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PublicationStatus::class,
            'remote_status' => RemotePublicationStatus::class,
            'remote_media_id' => 'integer',
            'payload' => 'array',
            'response_meta' => 'array',
            'attempt_count' => 'integer',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'published_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
