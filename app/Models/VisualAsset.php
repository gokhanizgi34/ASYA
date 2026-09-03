<?php

namespace App\Models;

use App\CopyrightStatus;
use App\VisualAssetStatus;
use App\VisualSourceType;
use Database\Factories\VisualAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['agency_id', 'article_id', 'uploaded_by', 'title', 'source_type', 'status', 'copyright_status', 'source_url', 'storage_disk', 'storage_path', 'mime_type', 'width', 'height', 'quality_score', 'alt_text', 'headline_overlay', 'generation_prompt', 'evaluation_notes', 'failure_message', 'is_selected', 'generated_at', 'evaluated_at'])]
class VisualAsset extends Model
{
    /** @use HasFactory<VisualAssetFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @param  Builder<VisualAsset>  $query
     * @return Builder<VisualAsset>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSystemAdministrator() ? $query : $query->where('agency_id', $user->agency_id);
    }

    public function hasPreview(): bool
    {
        return filled($this->storage_path) || filled($this->source_url);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_type' => VisualSourceType::class,
            'status' => VisualAssetStatus::class,
            'copyright_status' => CopyrightStatus::class,
            'width' => 'integer',
            'height' => 'integer',
            'quality_score' => 'integer',
            'is_selected' => 'boolean',
            'generated_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }
}
