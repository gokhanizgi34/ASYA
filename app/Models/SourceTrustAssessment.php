<?php

namespace App\Models;

use App\SourceTrustBand;
use Database\Factories\SourceTrustAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'news_source_id', 'assessed_by', 'identity_transparency', 'evidence_quality', 'correction_policy', 'historical_accuracy', 'editorial_independence', 'weighted_score', 'trust_band', 'notes', 'assessed_at'])]
class SourceTrustAssessment extends Model
{
    /** @use HasFactory<SourceTrustAssessmentFactory> */
    use HasFactory;

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'news_source_id');
    }

    /** @param Builder<SourceTrustAssessment> $query */
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
            'weighted_score' => 'float',
            'trust_band' => SourceTrustBand::class,
            'assessed_at' => 'datetime',
        ];
    }
}
