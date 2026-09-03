<?php

namespace App\Http\Requests;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateAiVisualEngineStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateAny', SystemSetting::class);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')],
            'enabled' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()?->isSystemAdministrator()) {
            $this->merge(['agency_id' => $this->user()?->agency_id]);
        }
    }
}
