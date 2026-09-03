<?php

namespace App\Http\Requests;

use App\Models\NewsSource;
use Illuminate\Foundation\Http\FormRequest;

class ImportNewsSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('newsSource');

        return $source instanceof NewsSource && ($this->user()?->can('update', $source) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
