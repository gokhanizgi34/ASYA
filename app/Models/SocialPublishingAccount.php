<?php

namespace App\Models;

use Database\Factories\SocialPublishingAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agency_id', 'created_by', 'name', 'platform', 'account_handle', 'access_token', 'api_key', 'api_secret', 'access_token_secret', 'refresh_token', 'token_expires_at', 'x_user_id', 'auth_type', 'publish_mode', 'mention_rules', 'is_active', 'last_published_at'])]
class SocialPublishingAccount extends Model
{
    protected $hidden = ['access_token', 'api_key', 'api_secret', 'access_token_secret', 'refresh_token'];

    /** @use HasFactory<SocialPublishingAccountFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    /** @param Builder<SocialPublishingAccount> $query */
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
            'access_token' => 'encrypted',
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'access_token_secret' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'mention_rules' => 'array',
            'is_active' => 'boolean',
            'last_published_at' => 'datetime',
        ];
    }
}
