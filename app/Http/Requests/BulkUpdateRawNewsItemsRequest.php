<?php

namespace App\Http\Requests;

use App\Models\RawNewsItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateRawNewsItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', RawNewsItem::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*' => ['required', 'integer', 'distinct', Rule::exists('raw_news_items', 'id')->whereNull('deleted_at')],
            'action' => ['required', Rule::in(['queue', 'reject', 'retry'])],
        ];
    }
}
