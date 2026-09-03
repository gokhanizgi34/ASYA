<?php

namespace App\Http\Requests;

use App\Models\Article;
use App\Models\SocialPost;
use App\Models\SocialPublishingAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SocialPost::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'social_publishing_account_id' => ['required', 'integer', Rule::exists('social_publishing_accounts', 'id')->where('is_active', true)],
            'article_id' => ['nullable', 'integer', Rule::exists('articles', 'id')],
            'content' => ['required', 'string', 'min:5', 'max:5000'],
            'link_url' => ['nullable', 'url:http,https', 'max:2048'],
            'media_url' => ['nullable', 'url:http,https', 'max:2048'],
            'scheduled_for' => ['nullable', 'date', 'after:now', 'before_or_equal:+1 year'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $account = SocialPublishingAccount::find($this->integer('social_publishing_account_id'));

            if (! $account || (! $this->user()?->isSystemAdministrator() && $account->agency_id !== $this->user()?->agency_id)) {
                $validator->errors()->add('social_publishing_account_id', 'Yayın hesabı bu ajans için kullanılamaz.');

                return;
            }

            if ($account->platform === 'x' && mb_strlen((string) $this->input('content')) > 280) {
                $validator->errors()->add('content', 'X gönderisi en fazla 280 karakter olabilir.');
            }

            if (filled($this->input('article_id'))) {
                $article = Article::find($this->integer('article_id'));

                if (! $article || $article->agency_id !== $account->agency_id) {
                    $validator->errors()->add('article_id', 'Haber yayın hesabıyla aynı ajansa ait olmalıdır.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $values = ['content' => trim((string) $this->input('content'))];

        foreach (['link_url', 'media_url'] as $field) {
            $values[$field] = filled($this->input($field)) ? trim((string) $this->input($field)) : null;
        }

        $this->merge($values);
    }
}
