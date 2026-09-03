<?php

namespace App\Http\Requests;

use App\Models\DatabaseBackup;
use Illuminate\Foundation\Http\FormRequest;

class StoreDatabaseBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DatabaseBackup::class) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['label' => ['nullable', 'string', 'max:100']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['label' => filled($this->input('label')) ? trim((string) $this->input('label')) : null]);
    }
}
