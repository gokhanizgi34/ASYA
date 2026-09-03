<?php

namespace App\Http\Requests;

use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;

class AnalyzeArticleSeoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof Article && ($this->user()?->can('update', $article) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'focus_keyword' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'focus_keyword' => filled($this->input('focus_keyword')) ? trim((string) $this->input('focus_keyword')) : null,
        ]);
    }
}
