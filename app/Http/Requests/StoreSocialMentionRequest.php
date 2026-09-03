<?php

namespace App\Http\Requests;

use App\Models\SocialListeningWatch;
use App\Models\SocialMention;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSocialMentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SocialMention::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'social_listening_watch_id' => ['required', 'integer', Rule::exists('social_listening_watches', 'id')->where('is_active', true)],
            'platform' => ['required', Rule::in(['x', 'instagram', 'facebook', 'youtube', 'tiktok', 'linkedin', 'web'])],
            'external_id' => ['nullable', 'string', 'max:190'],
            'author_handle' => ['nullable', 'string', 'max:120'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'title' => ['nullable', 'string', 'max:250'],
            'content' => ['required', 'string', 'min:20', 'max:20000'],
            'published_at' => ['required', 'date', 'before_or_equal:now'],
            'engagement_count' => ['required', 'integer', 'min:0', 'max:1000000000'],
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

            if (filled($this->input('external_id')) && SocialMention::query()
                ->where('agency_id', $watch->agency_id)
                ->where('platform', $this->input('platform'))
                ->where('external_id', $this->input('external_id'))
                ->exists()) {
                $validator->errors()->add('external_id', 'Bu sosyal medya bahsi daha önce kaydedilmiş.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        foreach (['external_id', 'author_handle', 'url', 'title'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field)) ?: null]);
            }
        }

        $this->merge([
            'content' => trim((string) $this->input('content')),
            'engagement_count' => $this->integer('engagement_count'),
        ]);
    }
}
