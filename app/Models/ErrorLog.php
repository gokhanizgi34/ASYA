<?php

namespace App\Models;

use App\ErrorLogStatus;
use App\ErrorSeverity;
use Database\Factories\ErrorLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'user_id', 'resolved_by_id', 'scope_key', 'fingerprint', 'severity', 'status', 'exception_class', 'message', 'file', 'line', 'http_method', 'path', 'route_name', 'occurrences', 'context', 'resolution_note', 'first_seen_at', 'last_seen_at', 'resolved_at'])]
class ErrorLog extends Model
{
    /** @use HasFactory<ErrorLogFactory> */
    use HasFactory;

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    /** @param Builder<ErrorLog> $query */
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
            'severity' => ErrorSeverity::class,
            'status' => ErrorLogStatus::class,
            'context' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
