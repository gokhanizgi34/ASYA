<?php

namespace App\Http\Requests;

use App\Models\NewsSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSourceTrustAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('newsSource');

        return $source instanceof NewsSource && ($this->user()?->can('update', $source) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'trust_score' => ['required', 'integer', Rule::in([10, 30, 50, 70, 90, 100])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'trust_score.required' => 'Güven puanı seçilmelidir.',
            'trust_score.in' => 'Güven puanı yalnızca 10, 30, 50, 70, 90 veya 100 olabilir.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'trust_score' => filled($this->input('trust_score')) ? (int) $this->input('trust_score') : null,
        ]);
    }
}
