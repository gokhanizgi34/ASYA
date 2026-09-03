<?php

namespace App\Http\Requests;

use App\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgencyStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agency = $this->route('agency');

        return $agency instanceof Agency && ($this->user()?->can('updateStatus', $agency) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
