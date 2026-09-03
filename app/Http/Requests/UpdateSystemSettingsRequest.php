<?php

namespace App\Http\Requests;

use App\Models\SystemSetting;
use App\Services\SystemSettingRegistry;
use App\SettingValueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateAny', SystemSetting::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(SystemSettingRegistry $registry): array
    {
        $rules = [
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')],
            'settings' => ['required', 'array'],
            'inherit' => ['nullable', 'array'],
        ];

        foreach ($registry->definitions() as $definition) {
            $fieldRules = ['required'];

            if ($definition['type'] === SettingValueType::Boolean) {
                $fieldRules[] = 'boolean';
            } elseif ($definition['type'] === SettingValueType::Integer) {
                $fieldRules[] = 'integer';
                $fieldRules[] = 'min:'.$definition['min'];
                $fieldRules[] = 'max:'.$definition['max'];
            } elseif ($definition['type'] === SettingValueType::Select) {
                $fieldRules[] = Rule::in(array_keys($definition['options']));
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:80';
            }

            $rules['settings.'.$definition['field']] = $fieldRules;
            $rules['inherit.'.$definition['field']] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;

        $this->merge(['agency_id' => filled($agencyId) ? (int) $agencyId : null]);
    }
}
