<?php

namespace App\Http\Requests;

use App\HttpMethod;
use App\Models\LearnedRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LearnedRouteFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LearnedRoute::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')],
            'method' => ['nullable', Rule::enum(HttpMethod::class)],
            'enabled' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'enabled' => filled($this->input('enabled')) ? $this->boolean('enabled') : null,
            'q' => filled($this->input('q')) ? trim((string) $this->input('q')) : null,
        ]);
    }
}
