<?php

namespace App\Models;

use Database\Factories\SeoAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'article_id', 'focus_keyword', 'meta_title', 'meta_description', 'keywords', 'hashtags', 'score', 'readability_score', 'word_count', 'keyword_density', 'issues', 'recommendations', 'analyzed_at'])]
class SeoAnalysis extends Model
{
    /** @use HasFactory<SeoAnalysisFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'hashtags' => 'array',
            'issues' => 'array',
            'recommendations' => 'array',
            'score' => 'integer',
            'readability_score' => 'integer',
            'word_count' => 'integer',
            'keyword_density' => 'float',
            'analyzed_at' => 'datetime',
        ];
    }
}
