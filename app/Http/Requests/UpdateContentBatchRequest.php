<?php

namespace App\Http\Requests;

use App\Models\ContentBatch;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContentBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('contentBatch');

        return $batch instanceof ContentBatch && ($this->user()?->can('update', $batch) ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:180']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }
}
