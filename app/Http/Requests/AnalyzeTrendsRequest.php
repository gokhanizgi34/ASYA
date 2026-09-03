<?php

namespace App\Http\Requests;

use App\Models\Agency;
use App\Models\TrendTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyzeTrendsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TrendTopic::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['agency_id' => ['required', 'integer', Rule::exists(Agency::class, 'id')->where('is_active', true)]];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()?->isSystemAdministrator()) {
            $this->merge(['agency_id' => $this->user()?->agency_id]);
        }
    }
}
