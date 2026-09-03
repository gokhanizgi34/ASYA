<?php

namespace App\Http\Requests;

use App\Models\SocialListeningWatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialListeningWatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SocialListeningWatch::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:120', Rule::unique('social_listening_watches', 'name')->where(fn ($query) => $query->where('agency_id', $this->input('agency_id')))],
            'keywords' => ['required', 'array', 'min:1', 'max:20'],
            'keywords.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'excluded_terms' => ['nullable', 'array', 'max:20'],
            'excluded_terms.*' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['required', Rule::in(['x', 'instagram', 'facebook', 'youtube', 'tiktok', 'linkedin', 'web'])],
            'alert_threshold' => ['required', 'integer', 'between:1,100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'name' => trim((string) $this->input('name')),
            'keywords' => $this->termList('keywords'),
            'excluded_terms' => $this->termList('excluded_terms'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /** @return array<int, string> */
    private function termList(string $field): array
    {
        $value = $this->input($field, []);

        if (! is_array($value)) {
            $value = explode(',', (string) $value);
        }

        return array_values(array_filter(array_map(fn (mixed $term): string => trim((string) $term), $value)));
    }
}
