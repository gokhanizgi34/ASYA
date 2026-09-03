<?php

namespace App\Models;

use App\TranslationStatus;
use Database\Factories\ArticleTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'article_id', 'created_by', 'reviewed_by', 'source_locale', 'target_locale', 'source_checksum', 'title', 'summary', 'body', 'glossary', 'status', 'reviewed_at'])]
class ArticleTranslation extends Model
{
    /** @use HasFactory<ArticleTranslationFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isSourceStale(): bool
    {
        $this->loadMissing('article');

        return $this->source_checksum !== hash('sha256', implode("\n", [
            $this->article->title,
            $this->article->summary,
            $this->article->body,
        ]));
    }

    /** @param Builder<ArticleTranslation> $query */
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
            'glossary' => 'array',
            'status' => TranslationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
