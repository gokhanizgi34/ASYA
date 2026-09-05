<?php

namespace App\Models;

use App\AdviceLetterStatus;
use App\AdviceRiskLevel;
use Database\Factories\AdviceLetterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['agency_id', 'submitted_by', 'answered_by', 'pseudonym', 'category', 'question', 'status', 'risk_level', 'risk_flags', 'publication_consent', 'response_title', 'response_body', 'answered_at', 'published_at'])]
class AdviceLetter extends Model
{
    /** @use HasFactory<AdviceLetterFactory> */
    use HasFactory, SoftDeletes;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /** @param Builder<AdviceLetter> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSystemAdministrator() ? $query : $query->where('agency_id', $user->agency_id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'question' => 'encrypted',
            'response_body' => 'encrypted',
            'status' => AdviceLetterStatus::class,
            'risk_level' => AdviceRiskLevel::class,
            'risk_flags' => 'array',
            'publication_consent' => 'boolean',
            'answered_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
