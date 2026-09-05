<?php

namespace App\Http\Requests;

use App\Models\EditorialStyleProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEditorialStyleProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EditorialStyleProfile::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:120'], 'sample_text' => ['nullable', 'string', 'max:50000'],
            'preferred_terms' => ['nullable', 'string', 'max:10000'], 'replacements_text' => ['nullable', 'string', 'max:20000'],
            'forbidden_terms_text' => ['nullable', 'string', 'max:10000'], 'daily_quota' => ['required', 'integer', 'between:0,500'],
            'destination' => ['required', Rule::in(['publish', 'draft'])], 'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;
        $this->merge(['agency_id' => filled($agencyId) ? (int) $agencyId : null, 'name' => trim((string) $this->input('name', 'Ajans yazım dili')), 'is_active' => $this->boolean('is_active')]);
    }
}
