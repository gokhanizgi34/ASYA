<?php

namespace App\Http\Requests;

use App\Models\NewsSource;
use App\Services\ExternalUrlGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class UpdateNewsSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('newsSource');

        return $source instanceof NewsSource && ($this->user()?->can('update', $source) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $source = $this->route('newsSource');

        return [
            'name' => ['required', 'string', 'max:160'],
            'domain' => [
                'required',
                'string',
                'max:190',
                Rule::unique('news_sources')
                    ->where(fn ($query) => $query->where('agency_id', $source instanceof NewsSource ? $source->agency_id : null))
                    ->ignore($source),
            ],
            'feed_url' => ['required', 'url:http,https', 'max:2048'],
            'allow_insecure_tls' => ['boolean'],
            'feed_format' => ['required', Rule::in(['auto', 'rss', 'atom'])],
            'source_type' => ['required', Rule::in(['news_site', 'official', 'agency', 'expert', 'social', 'other'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
            'daily_item_limit' => ['required', 'integer', 'between:1,100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('feed_url')) {
                return;
            }

            try {
                app(ExternalUrlGuard::class)->assertSafe((string) $this->input('feed_url'), false);
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('feed_url', $exception->getMessage());
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $feedUrl = trim((string) $this->input('feed_url'));
        $host = parse_url($feedUrl, PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', mb_strtolower((string) $host));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'domain' => $domain,
            'feed_url' => $feedUrl,
            'allow_insecure_tls' => $this->boolean('allow_insecure_tls'),
            'feed_format' => $this->input('feed_format', 'auto'),
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
            'is_active' => $this->boolean('is_active'),
            'daily_item_limit' => $this->integer('daily_item_limit', 10),
        ]);
    }
}
