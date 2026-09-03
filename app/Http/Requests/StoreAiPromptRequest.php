<?php

namespace App\Http\Requests;

use App\Models\AiPrompt;
use App\PromptTone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAiPromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AiPrompt::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'scope_key' => ['required', 'string', 'max:100'],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('ai_prompts', 'name')
                    ->where(fn ($query) => $query->where('scope_key', $this->input('scope_key')))
                    ->ignore($this->promptForUniqueRule()),
            ],
            'category' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\-]+$/'],
            'tone' => ['required', Rule::enum(PromptTone::class)],
            'language' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
            'target_length' => ['required', 'integer', 'between:100,5000'],
            'temperature' => ['required', 'numeric', 'between:0,2'],
            'system_prompt' => ['required', 'string', 'min:10', 'max:10000'],
            'user_prompt_template' => ['required', 'string', 'min:10', 'max:50000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! str_contains((string) $this->input('user_prompt_template'), '{content}')) {
                $validator->errors()->add('user_prompt_template', 'Kullanıcı prompt şablonunda {content} yer tutucusu bulunmalıdır.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator()
            ? $this->input('agency_id')
            : $this->user()?->agency_id;
        $agencyId = filled($agencyId) ? (int) $agencyId : null;

        $this->merge([
            'agency_id' => $agencyId,
            'scope_key' => $agencyId === null ? 'global' : 'agency:'.$agencyId,
            'name' => trim((string) $this->input('name')),
            'category' => strtolower(trim((string) $this->input('category'))),
            'language' => trim((string) $this->input('language', 'tr')),
            'system_prompt' => trim((string) $this->input('system_prompt')),
            'user_prompt_template' => trim((string) $this->input('user_prompt_template')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    protected function promptForUniqueRule(): ?AiPrompt
    {
        return null;
    }
}
