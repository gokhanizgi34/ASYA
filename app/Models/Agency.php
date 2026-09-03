<?php

namespace App\Models;

use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'contact_email', 'phone', 'subscription_starts_at', 'subscription_ends_at', 'trial_ends_at', 'province', 'district', 'category_name', 'logo_path', 'is_active'])]
class Agency extends Model
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscription_starts_at' => 'date',
            'subscription_ends_at' => 'date',
            'trial_ends_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function hasActiveSubscription(): bool
    {
        return $this->is_active
            && ($this->subscription_starts_at === null || $this->subscription_starts_at->startOfDay()->lte(today()))
            && ($this->subscription_ends_at === null || $this->subscription_ends_at->endOfDay()->gte(now()));
    }
}
