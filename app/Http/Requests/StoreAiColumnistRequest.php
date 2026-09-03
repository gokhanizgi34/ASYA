<?php

namespace App\Http\Requests;

use App\Models\AiColumnist;
use App\Models\AiPrompt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAiColumnistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AiColumnist::class) ?? false;
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return ['agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)], 'ai_prompt_id' => ['nullable', 'integer', Rule::exists('ai_prompts', 'id')->whereNull('deleted_at')], 'name' => ['required', 'string', 'max:120'], 'slug' => ['required', 'string', 'max:140', Rule::unique('ai_columnists', 'slug')->where(fn ($q) => $q->where('agency_id', $this->input('agency_id')))->ignore($this->columnist())], 'pen_name' => ['required', 'string', 'max:120'], 'biography' => ['required', 'string', 'min:20', 'max:2000'], 'expertise' => ['required', 'array', 'min:1', 'max:10'], 'expertise.*' => ['string', 'max:60', 'distinct'], 'voice_guide' => ['required', 'string', 'min:30', 'max:5000'], 'disclosure' => ['required', 'string', 'min:20', 'max:255'], 'is_active' => ['required', 'boolean']];
    }

    /** @return array<int,callable(Validator):void> */
    public function after(): array
    {
        return [function (Validator $v): void {
            if (filled($this->input('ai_prompt_id'))) {
                $p = AiPrompt::find($this->integer('ai_prompt_id'));
                if (! $p || ($p->agency_id !== null && $p->agency_id !== $this->integer('agency_id'))) {
                    $v->errors()->add('ai_prompt_id', 'Prompt bu ajans için kullanılamaz.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agency = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;
        $name = trim((string) $this->input('name'));
        $expertise = is_array($this->input('expertise')) ? $this->input('expertise') : explode(',', (string) $this->input('expertise'));
        $this->merge(['agency_id' => filled($agency) ? (int) $agency : null, 'name' => $name, 'slug' => Str::slug((string) $this->input('slug', $name)), 'pen_name' => trim((string) $this->input('pen_name')), 'expertise' => array_values(array_filter(array_map(fn ($v) => trim((string) $v), $expertise))), 'biography' => trim((string) $this->input('biography')), 'voice_guide' => trim((string) $this->input('voice_guide')), 'disclosure' => trim((string) $this->input('disclosure')), 'is_active' => $this->boolean('is_active')]);
    }

    protected function columnist(): ?AiColumnist
    {
        return null;
    }
}
