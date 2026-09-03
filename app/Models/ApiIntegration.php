<?php

namespace App\Models;

use App\IntegrationAuthType;
use App\IntegrationProvider;
use Database\Factories\ApiIntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['agency_id', 'name', 'provider', 'model', 'priority', 'is_default', 'visual_enabled', 'base_url', 'auth_type', 'username', 'api_key_header', 'credential', 'timeout_seconds', 'is_active', 'last_tested_at', 'last_status_code', 'last_response_time_ms', 'last_error'])]
#[Hidden(['credential'])]
class ApiIntegration extends Model
{
    /** @use HasFactory<ApiIntegrationFactory> */
    use HasFactory, SoftDeletes;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @param Builder<ApiIntegration> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSystemAdministrator()) {
            $query->where('agency_id', $user->agency_id);
        }
    }

    /** @param Builder<ApiIntegration> $query */
    public function scopeAi(Builder $query): void
    {
        $query->whereIn(
            'provider',
            collect(IntegrationProvider::cases())->filter->isAi()->pluck('value'),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider' => IntegrationProvider::class,
            'auth_type' => IntegrationAuthType::class,
            'credential' => 'encrypted',
            'priority' => 'integer',
            'is_default' => 'boolean',
            'visual_enabled' => 'boolean',
            'timeout_seconds' => 'integer',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
