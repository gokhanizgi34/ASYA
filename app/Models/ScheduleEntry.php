<?php

namespace App\Models;

use App\ScheduleAction;
use App\ScheduleStatus;
use Database\Factories\ScheduleEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'created_by', 'publication_id', 'campaign_id', 'action', 'status', 'active_key', 'title', 'scheduled_for', 'timezone', 'attempt_count', 'failure_message', 'started_at', 'completed_at'])]
class ScheduleEntry extends Model
{
    /** @use HasFactory<ScheduleEntryFactory> */
    use HasFactory;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Publication, $this> */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @param Builder<ScheduleEntry> $query @return Builder<ScheduleEntry> */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isSystemAdministrator() ? $query : $query->where('agency_id', $user->agency_id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['action' => ScheduleAction::class, 'status' => ScheduleStatus::class, 'scheduled_for' => 'datetime', 'attempt_count' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
