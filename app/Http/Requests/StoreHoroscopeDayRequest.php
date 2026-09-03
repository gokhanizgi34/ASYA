<?php

namespace App\Http\Requests;

use App\Models\HoroscopeForecast;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHoroscopeDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', HoroscopeForecast::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)], 'forecast_date' => ['required', 'date', 'after_or_equal:-30 days', 'before_or_equal:+365 days']];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;
        $this->merge(['agency_id' => filled($agencyId) ? (int) $agencyId : null]);
    }
}
