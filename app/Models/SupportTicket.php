<?php

namespace App\Models;

use App\SupportTicketPriority;
use App\SupportTicketStatus;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['agency_id', 'user_id', 'handled_by', 'ticket_number', 'category', 'priority', 'status', 'subject', 'message', 'admin_note', 'handled_at'])]
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            $ticket->ticket_number ??= 'DST-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        });
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** @param Builder<SupportTicket> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isSystemAdministrator()) {
            return;
        }

        if ($user->isAgencyOwner()) {
            $query->where('agency_id', $user->agency_id);

            return;
        }

        $query->where('user_id', $user->id);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'priority' => SupportTicketPriority::class,
            'status' => SupportTicketStatus::class,
            'handled_at' => 'datetime',
        ];
    }
}
