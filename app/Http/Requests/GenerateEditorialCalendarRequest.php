<?php

namespace App\Http\Requests;

use App\Models\ScheduleEntry;
use Illuminate\Foundation\Http\FormRequest;

class GenerateEditorialCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ScheduleEntry::class) === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $currentYear = now()->year;

        return ['agency_id' => ['required', 'integer', 'exists:agencies,id'], 'start_year' => ['required', 'integer', 'min:'.$currentYear, 'max:'.($currentYear + 5)], 'years' => ['required', 'integer', 'min:1', 'max:5']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['start_year' => $this->input('start_year', now()->year), 'years' => $this->input('years', 5)]);
    }
}
