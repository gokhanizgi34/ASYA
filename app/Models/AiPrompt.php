<?php

namespace App\Models;

use App\PromptTone;
use Database\Factories\AiPromptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['agency_id', 'name', 'category', 'tone', 'language', 'target_length', 'temperature', 'system_prompt', 'user_prompt_template', 'is_active', 'version', 'last_tested_at'])]
class AiPrompt extends Model
{
    /** @use HasFactory<AiPromptFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @param  Builder<AiPrompt>  $query
     * @return Builder<AiPrompt>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSystemAdministrator()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->whereNull('agency_id')->orWhere('agency_id', $user->agency_id);
        });
    }

    protected static function booted(): void
    {
        static::saving(function (AiPrompt $prompt): void {
            $prompt->scope_key = $prompt->agency_id === null ? 'global' : 'agency:'.$prompt->agency_id;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tone' => PromptTone::class,
            'target_length' => 'integer',
            'temperature' => 'decimal:2',
            'is_active' => 'boolean',
            'version' => 'integer',
            'last_tested_at' => 'datetime',
        ];
    }
}
