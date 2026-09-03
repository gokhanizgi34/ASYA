<?php

namespace App\Models;

use App\HoroscopeStatus;
use App\ZodiacSign;
use Database\Factories\HoroscopeForecastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['agency_id', 'created_by', 'updated_by', 'forecast_date', 'sign', 'symbol', 'status', 'general', 'traits', 'rising', 'love', 'career', 'money', 'health', 'seo_title', 'seo_description', 'seo_keywords', 'lucky_color', 'lucky_number', 'published_at'])]
class HoroscopeForecast extends Model
{
    /** @use HasFactory<HoroscopeForecastFactory> */
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @param Builder<HoroscopeForecast> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSystemAdministrator()) {
            $query->where('agency_id', $user->agency_id);
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['forecast_date' => 'date:Y-m-d', 'sign' => ZodiacSign::class, 'status' => HoroscopeStatus::class, 'lucky_number' => 'integer', 'seo_keywords' => 'array', 'published_at' => 'datetime'];
    }
}
