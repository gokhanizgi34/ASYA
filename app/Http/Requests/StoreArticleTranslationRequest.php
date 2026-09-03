<?php

namespace App\Http\Requests;

use App\Models\Article;
use App\Models\ArticleTranslation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreArticleTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ArticleTranslation::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'article_id' => ['required', 'integer', Rule::exists('articles', 'id')->whereNull('deleted_at')],
            'target_locale' => ['required', Rule::in(['en', 'de', 'fr', 'es', 'ar', 'ru'])],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $article = Article::find($this->integer('article_id'));

            if (! $article || (! $this->user()?->isSystemAdministrator() && $article->agency_id !== $this->user()?->agency_id)) {
                $validator->errors()->add('article_id', 'Haber bu ajans için kullanılamaz.');

                return;
            }

            if (ArticleTranslation::query()->where('article_id', $article->id)->where('target_locale', $this->input('target_locale'))->exists()) {
                $validator->errors()->add('target_locale', 'Bu haber için hedef dil çevirisi zaten var.');
            }
        }];
    }
}
