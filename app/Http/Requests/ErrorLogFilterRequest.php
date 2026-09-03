<?php

namespace App\Http\Requests;

use App\ErrorLogStatus;
use App\ErrorSeverity;
use App\Models\ErrorLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ErrorLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ErrorLog::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(ErrorLogStatus::class)],
            'severity' => ['nullable', Rule::enum(ErrorSeverity::class)],
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'before_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'q' => filled($this->input('q')) ? trim((string) $this->input('q')) : null,
        ]);
    }
}
