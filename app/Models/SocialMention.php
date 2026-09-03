<?php

namespace App\Models;

use App\SocialMentionStatus;
use App\SocialSentiment;
use Database\Factories\SocialMentionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'social_listening_watch_id', 'created_by', 'platform', 'external_id', 'author_handle', 'url', 'title', 'content', 'published_at', 'engagement_count', 'sentiment', 'sentiment_score', 'urgency_score', 'matched_keywords', 'status'])]
class SocialMention extends Model
{
    /** @use HasFactory<SocialMentionFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function watch(): BelongsTo
    {
        return $this->belongsTo(SocialListeningWatch::class, 'social_listening_watch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<SocialMention> $query */
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
            'published_at' => 'datetime',
            'engagement_count' => 'integer',
            'sentiment' => SocialSentiment::class,
            'sentiment_score' => 'float',
            'urgency_score' => 'integer',
            'matched_keywords' => 'array',
            'status' => SocialMentionStatus::class,
        ];
    }
}
