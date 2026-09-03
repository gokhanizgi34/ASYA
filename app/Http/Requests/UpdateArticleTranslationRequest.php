<?php

namespace App\Http\Requests;

use App\Models\ArticleTranslation;
use App\TranslationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateArticleTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $translation = $this->route('articleTranslation');

        return $translation instanceof ArticleTranslation && ($this->user()?->can('update', $translation) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'summary' => ['nullable', 'string', 'max:3000'],
            'body' => ['required', 'string', 'min:50', 'max:100000'],
            'glossary' => ['nullable', 'array', 'max:100'],
            'glossary.*' => ['string', 'max:250'],
            'status' => ['required', Rule::enum(TranslationStatus::class)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $status = TranslationStatus::tryFrom((string) $this->input('status'));
            $translation = $this->route('articleTranslation');

            if (! $this->user()?->isSystemAdministrator() && ! $this->user()?->isAgencyOwner() && in_array($status, [TranslationStatus::Approved, TranslationStatus::Rejected], true)) {
                $validator->errors()->add('status', 'Editör çeviriyi onaylayamaz veya reddedemez.');
            }

            if ($status === TranslationStatus::Approved && $translation instanceof ArticleTranslation && $translation->isSourceStale()) {
                $validator->errors()->add('status', 'Kaynak haber değişti. Onaydan önce çeviri taslağını yenileyin.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $glossary = $this->input('glossary', []);

        if (! is_array($glossary)) {
            $pairs = preg_split('/\R/', (string) $glossary) ?: [];
            $glossary = [];

            foreach ($pairs as $pair) {
                [$source, $target] = array_pad(explode('=', $pair, 2), 2, null);

                if (filled($source) && filled($target)) {
                    $glossary[trim($source)] = trim($target);
                }
            }
        }

        $this->merge([
            'title' => trim((string) $this->input('title')),
            'summary' => filled($this->input('summary')) ? trim((string) $this->input('summary')) : null,
            'body' => trim((string) $this->input('body')),
            'glossary' => $glossary,
        ]);
    }
}
