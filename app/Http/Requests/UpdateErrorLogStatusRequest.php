<?php

namespace App\Http\Requests;

use App\Models\ErrorLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateErrorLogStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $errorLog = $this->route('errorLog');

        return $errorLog instanceof ErrorLog && ($this->user()?->can('update', $errorLog) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(['resolve', 'reopen', 'ignore'])],
            'resolution_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn (): bool => in_array($this->input('operation'), ['resolve', 'ignore'], true))],
        ];
    }
}
