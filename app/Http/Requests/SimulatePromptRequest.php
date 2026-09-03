<?php

namespace App\Http\Requests;

use App\Models\AiPrompt;
use App\Services\PromptSimulator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SimulatePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $prompt = $this->route('aiPrompt');

        return $prompt instanceof AiPrompt
            && ($this->user()?->can('viewAny', AiPrompt::class) ?? false)
            && ($this->user()?->can('view', $prompt) ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(PromptSimulator $simulator): array
    {
        $rules = ['variables' => ['required', 'array', 'max:20']];
        $prompt = $this->route('aiPrompt');

        if ($prompt instanceof AiPrompt) {
            foreach ($simulator->variables($prompt) as $variable) {
                $rules['variables.'.$variable] = ['required', 'string', 'max:10000'];
            }
        }

        return $rules;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(PromptSimulator $simulator): array
    {
        return [function (Validator $validator) use ($simulator): void {
            $prompt = $this->route('aiPrompt');
            if (! $prompt instanceof AiPrompt) {
                return;
            }

            $allowed = $simulator->variables($prompt);
            $submitted = array_keys((array) $this->input('variables', []));

            if (array_diff($submitted, $allowed) !== []) {
                $validator->errors()->add('variables', 'Şablonda bulunmayan bir değişken gönderildi.');
            }

            $totalLength = array_sum(array_map(fn (mixed $value): int => mb_strlen((string) $value), (array) $this->input('variables', [])));
            if ($totalLength > 30000) {
                $validator->errors()->add('variables', 'Simülasyon değişkenleri toplam 30.000 karakteri aşamaz.');
            }
        }];
    }
}
