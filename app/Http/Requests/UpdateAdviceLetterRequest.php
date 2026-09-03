<?php

namespace App\Http\Requests;

use App\AdviceLetterStatus;
use App\AdviceRiskLevel;
use App\Models\AdviceLetter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'response_title' => ['nullable', 'string', 'max:180'],
            'response_body' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $status = AdviceLetterStatus::tryFrom((string) $this->input('status'));
            $letter = $this->route('adviceLetter');

            if (in_array($status, [AdviceLetterStatus::Answered, AdviceLetterStatus::Published], true)) {
                if (mb_strlen((string) $this->input('response_title')) < 5) {
                    $validator->errors()->add('response_title', 'Yanıtlanan mektup için en az 5 karakterlik başlık gereklidir.');
                }
                if (mb_strlen((string) $this->input('response_body')) < 100) {
                    $validator->errors()->add('response_body', 'Yanıt en az 100 karakter olmalıdır.');
                }
            }

            if ($letter instanceof AdviceLetter && $letter->risk_level === AdviceRiskLevel::Critical && in_array($status, [AdviceLetterStatus::Answered, AdviceLetterStatus::Published], true)) {
                $validator->errors()->add('status', 'Kritik güvenlik riski taşıyan mektup yayımlanamaz; uzman incelemesine yönlendirilmelidir.');
            }

            if ($letter instanceof AdviceLetter && $status === AdviceLetterStatus::Published && ! $letter->publication_consent) {
                $validator->errors()->add('status', 'Yayın izni bulunmayan mektup yayımlanamaz.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'response_title' => filled($this->input('response_title')) ? trim((string) $this->input('response_title')) : null,
            'response_body' => filled($this->input('response_body')) ? trim((string) $this->input('response_body')) : null,
        ]);
    }
}
