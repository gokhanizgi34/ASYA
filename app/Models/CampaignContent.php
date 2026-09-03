<?php

namespace App\Models;

use App\CampaignChannel;
use App\CampaignContentStatus;
use Database\Factories\CampaignContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['campaign_id', 'article_id', 'created_by', 'channel', 'status', 'title', 'body', 'call_to_action', 'destination_url', 'metadata', 'approved_at', 'published_at'])]
class CampaignContent extends Model
{
    /** @use HasFactory<CampaignContentFactory> */
    use HasFactory;

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['channel' => CampaignChannel::class, 'status' => CampaignContentStatus::class, 'metadata' => 'array', 'approved_at' => 'datetime', 'published_at' => 'datetime'];
    }
}
