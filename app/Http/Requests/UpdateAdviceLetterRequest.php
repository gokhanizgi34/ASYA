<?php

namespace App\Http\Requests;

use App\AdviceLetterStatus;
use App\Models\AdviceLetter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdviceLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $letter = $this->route('adviceLetter');

        return $letter instanceof AdviceLetter && ($this->user()?->can('update', $letter) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AdviceLetterStatus::class)],
            'response_title' => ['required', 'string', 'min:5', 'max:180'],
            'response_body' => ['required', 'string', 'min:100', 'max:20000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'response_title' => trim((string) $this->input('response_title')),
            'response_body' => trim((string) $this->input('response_body')),
        ]);
    }
}
