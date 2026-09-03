<?php

namespace App\Http\Requests;

use App\Models\RawNewsItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAllRawNewsItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RawNewsItem::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['queue_all', 'retry_all'])],
        ];
    }
}
