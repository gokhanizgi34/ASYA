<?php

namespace App\Models;

use App\BlacklistAction;
use App\BlacklistRuleType;
use Database\Factories\BlacklistRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'created_by', 'type', 'pattern', 'normalized_pattern', 'action', 'reason', 'hit_count', 'last_hit_at', 'is_active'])]
class BlacklistRule extends Model
{
    /** @use HasFactory<BlacklistRuleFactory> */
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

    /** @param Builder<BlacklistRule> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSystemAdministrator()) {
            $query->where('agency_id', $user->agency_id);
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => BlacklistRuleType::class, 'action' => BlacklistAction::class, 'last_hit_at' => 'datetime', 'is_active' => 'boolean'];
    }
}
