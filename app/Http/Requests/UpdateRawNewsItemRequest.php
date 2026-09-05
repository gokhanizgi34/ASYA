<?php

namespace App\Http\Requests;

use App\Models\RawNewsItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateRawNewsItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('rawNewsItem');

        return $item instanceof RawNewsItem && ($this->user()?->can('update', $item) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $item = $this->route('rawNewsItem');

        return [
            'source_name' => ['required', 'string', 'max:255'], 'source_url' => ['nullable', 'url:http,https', 'max:2000'],
            'external_id' => ['nullable', 'string', 'max:255'], 'original_title' => ['required', 'string', 'max:500'],
            'original_body' => ['required', 'string', 'min:20'], 'original_image_url' => ['nullable', 'url:http,https', 'max:2000'],
            'language' => ['required', 'string', 'regex:/^[a-z]{2,3}(?:-[A-Z]{2})?$/', 'max:10'], 'priority' => ['required', 'integer', 'between:0,100'],
            'checksum' => ['required', 'size:64', Rule::unique('raw_news_items', 'checksum')->where(fn ($query) => $query->where('agency_id', $item?->agency_id))->ignore($item?->id)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $title = trim((string) $this->input('original_title'));
        $sourceName = trim((string) $this->input('source_name'));
        $sourceUrl = filled($this->input('source_url')) ? trim((string) $this->input('source_url')) : null;
        $this->merge([
            'external_id' => filled($this->input('external_id')) ? trim((string) $this->input('external_id')) : null,
            'source_name' => $sourceName, 'source_url' => $sourceUrl, 'original_title' => $title,
            'original_body' => trim((string) $this->input('original_body')),
            'original_image_url' => filled($this->input('original_image_url')) ? trim((string) $this->input('original_image_url')) : null,
            'language' => trim((string) $this->input('language', 'tr')), 'priority' => $this->integer('priority', 50),
            'checksum' => hash('sha256', Str::lower($sourceUrl ?: $sourceName).'|'.Str::lower($title)),
        ]);
    }
}
