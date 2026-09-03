<?php

namespace App\Http\Requests;

use App\Models\SocialFeedSource;
use App\Models\SocialListeningWatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSocialFeedSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SocialFeedSource::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'social_listening_watch_id' => ['required', 'integer', Rule::exists('social_listening_watches', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:120'],
            'platform' => ['required', Rule::in(['x', 'instagram', 'facebook', 'youtube', 'tiktok', 'linkedin', 'web'])],
            'endpoint_url' => ['nullable', 'url:http,https', 'max:2048'],
            'auth_secret' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $watch = SocialListeningWatch::find($this->integer('social_listening_watch_id'));

            if (! $watch || (! $this->user()?->isSystemAdministrator() && $watch->agency_id !== $this->user()?->agency_id)) {
                $validator->errors()->add('social_listening_watch_id', 'Dinleme kuralı bu ajans için kullanılamaz.');

                return;
            }

            if (! in_array($this->input('platform'), $watch->platforms, true)) {
                $validator->errors()->add('platform', 'Platform bu dinleme kuralında etkin değildir.');
            }

            if (SocialFeedSource::query()->where('agency_id', $watch->agency_id)->where('name', $this->input('name'))->exists()) {
                $validator->errors()->add('name', 'Bu ajans için kaynak adı daha önce kullanılmış.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'endpoint_url' => filled($this->input('endpoint_url')) ? trim((string) $this->input('endpoint_url')) : null,
            'auth_secret' => filled($this->input('auth_secret')) ? trim((string) $this->input('auth_secret')) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
