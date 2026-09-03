<?php

namespace App\Models;

use App\ErrorSeverity;
use Database\Factories\SystemNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'recipient_user_id', 'type', 'severity', 'title', 'message', 'action_route', 'action_parameters', 'fingerprint', 'occurrences', 'first_occurred_at', 'last_occurred_at', 'read_at'])]
class SystemNotification extends Model
{
    /** @use HasFactory<SystemNotificationFactory> */
    use HasFactory;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** @param Builder<SystemNotification> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where('recipient_user_id', $user->id);
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => now()]);
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'severity' => ErrorSeverity::class,
            'action_parameters' => 'array',
            'occurrences' => 'integer',
            'first_occurred_at' => 'datetime',
            'last_occurred_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
