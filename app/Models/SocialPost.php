<?php

namespace App\Models;

use App\SocialPostStatus;
use Database\Factories\SocialPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'social_publishing_account_id', 'article_id', 'created_by', 'content', 'link_url', 'media_url', 'scheduled_for', 'status', 'external_id', 'error_message', 'published_at'])]
class SocialPost extends Model
{
    /** @use HasFactory<SocialPostFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialPublishingAccount::class, 'social_publishing_account_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** @param Builder<SocialPost> $query */
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
            'scheduled_for' => 'datetime',
            'status' => SocialPostStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
