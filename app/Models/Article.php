<?php

namespace App\Models;

use App\ArticleStatus;
use App\SourceTrustStatus;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['agency_id', 'author_id', 'title', 'slug', 'summary', 'body', 'editorial_metadata', 'status', 'source_trust_status', 'source_name', 'source_url', 'published_at', 'failure_message'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasOne<SeoAnalysis, $this> */
    public function seoAnalysis(): HasOne
    {
        return $this->hasOne(SeoAnalysis::class);
    }

    /** @return HasMany<VisualAsset, $this> */
    public function visualAssets(): HasMany
    {
        return $this->hasMany(VisualAsset::class);
    }

    /** @return HasOne<VisualAsset, $this> */
    public function selectedVisualAsset(): HasOne
    {
        return $this->hasOne(VisualAsset::class)->where('is_selected', true);
    }

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSystemAdministrator()) {
            return $query;
        }

        return $query->where('agency_id', $user->agency_id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'source_trust_status' => SourceTrustStatus::class,
            'editorial_metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
