<?php

namespace App\Http\Requests;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateGoogleTrendQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateAny', SystemSetting::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')],
            'daily_limit' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()?->isSystemAdministrator()) {
            $this->merge(['agency_id' => $this->user()?->agency_id]);
        }
    }
}
