<?php

namespace App\Http\Requests;

use App\Models\AdviceLetter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdviceLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AdviceLetter::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'pseudonym' => ['required', 'string', 'min:2', 'max:80'],
            'category' => ['required', Rule::in(['relationship', 'family', 'work', 'personal', 'other'])],
            'question' => ['required', 'string', 'min:50', 'max:5000'],
            'publication_consent' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'pseudonym' => trim((string) $this->input('pseudonym')),
            'question' => trim((string) $this->input('question')),
            'publication_consent' => $this->boolean('publication_consent'),
        ]);
    }
}
