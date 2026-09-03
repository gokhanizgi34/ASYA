<?php

namespace App\Models;

use Database\Factories\EditorialCalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'created_by', 'event_date', 'content_due_at', 'title', 'seo_topics', 'status', 'ai_provider'])]
class EditorialCalendarEvent extends Model
{
    /** @use HasFactory<EditorialCalendarEventFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<EditorialCalendarEvent> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSystemAdministrator()) {
            $query->where('agency_id', $user->agency_id);
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['event_date' => 'date', 'content_due_at' => 'date', 'seo_topics' => 'array'];
    }
}
