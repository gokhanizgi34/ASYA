<?php

namespace App\Models;

use App\MailTransportScheme;
use Database\Factories\AgencyMailSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'updated_by', 'host', 'port', 'scheme', 'username', 'password', 'from_address', 'from_name', 'notification_email', 'is_active', 'last_tested_at', 'last_error'])]
#[Hidden(['password'])]
class AgencyMailSetting extends Model
{
    /** @use HasFactory<AgencyMailSettingFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @param Builder<AgencyMailSetting> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSystemAdministrator()) {
            $query->where('agency_id', $user->agency_id);
        }
    }

    /** @return array<string, mixed> */
    public function mailerConfig(): array
    {
        return [
            'transport' => 'smtp',
            'scheme' => $this->scheme->value,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => 10,
            'from' => [
                'address' => $this->from_address,
                'name' => $this->from_name,
            ],
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'port' => 'integer',
            'scheme' => MailTransportScheme::class,
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
