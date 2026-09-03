<?php

namespace App\Models;

use Database\Factories\AnalyticsSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'report_date', 'raw_news_count', 'articles_created_count', 'articles_published_count', 'publication_success_count', 'publication_failure_count', 'campaigns_created_count', 'campaign_contents_count', 'trend_topics_count', 'seo_word_count', 'average_seo_score', 'average_trend_score', 'details', 'aggregated_at'])]
class AnalyticsSnapshot extends Model
{
    /** @use HasFactory<AnalyticsSnapshotFactory> */
    use HasFactory;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @param Builder<AnalyticsSnapshot> $query @return Builder<AnalyticsSnapshot> */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSystemAdministrator() ? $query : $query->where('agency_id', $user->agency_id);
    }

    public function publicationSuccessRate(): float
    {
        $total = $this->publication_success_count + $this->publication_failure_count;

        return $total === 0 ? 0 : round(($this->publication_success_count / $total) * 100, 2);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['report_date' => 'date', 'average_seo_score' => 'float', 'average_trend_score' => 'float', 'details' => 'array', 'aggregated_at' => 'datetime'];
    }
}
