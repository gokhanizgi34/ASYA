<?php

namespace App\Http\Requests;

use App\CopyrightStatus;
use App\Models\Article;
use App\Models\VisualAsset;
use App\VisualSourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreVisualAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', VisualAsset::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'article_id' => ['nullable', 'integer', Rule::exists('articles', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:180'],
            'source_type' => ['required', Rule::enum(VisualSourceType::class)],
            'copyright_status' => ['required', Rule::enum(CopyrightStatus::class)],
            'image' => ['nullable', File::image()->max('10mb')],
            'source_url' => ['nullable', 'url:http,https', 'max:2000'],
            'width' => ['nullable', 'integer', 'between:1,20000'],
            'height' => ['nullable', 'integer', 'between:1,20000'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'headline_overlay' => ['nullable', 'string', 'max:120'],
            'generation_prompt' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $sourceType = VisualSourceType::tryFrom((string) $this->input('source_type'));

            if ($sourceType === VisualSourceType::Upload && ! $this->hasFile('image')) {
                $validator->errors()->add('image', 'Manuel yükleme için bir görsel dosyası seçilmelidir.');
            }

            if (in_array($sourceType, [VisualSourceType::Original, VisualSourceType::Archive], true) && blank($this->input('source_url'))) {
                $validator->errors()->add('source_url', 'Bu görsel kaynağı için bağlantı gereklidir.');
            }

            if ($sourceType === VisualSourceType::AiGenerated && blank($this->input('generation_prompt'))) {
                $validator->errors()->add('generation_prompt', 'Özgün görsel üretimi için üretim promptu gereklidir.');
            }

            $articleId = $this->integer('article_id');
            if ($articleId === 0) {
                return;
            }

            $article = Article::query()->find($articleId);
            if ($article && (int) $article->agency_id !== $this->integer('agency_id')) {
                $validator->errors()->add('article_id', 'Haber ile görsel aynı ajansa ait olmalıdır.');
            }

            if ($article && ! ($this->user()?->can('view', $article) ?? false)) {
                $validator->errors()->add('article_id', 'Bu habere görsel ekleme yetkiniz yok.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator()
            ? $this->input('agency_id')
            : $this->user()?->agency_id;

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'article_id' => filled($this->input('article_id')) ? (int) $this->input('article_id') : null,
            'title' => trim((string) $this->input('title')),
            'copyright_status' => $this->input('copyright_status', CopyrightStatus::Unknown->value),
            'source_url' => filled($this->input('source_url')) ? trim((string) $this->input('source_url')) : null,
            'alt_text' => filled($this->input('alt_text')) ? trim((string) $this->input('alt_text')) : null,
            'headline_overlay' => filled($this->input('headline_overlay')) ? trim((string) $this->input('headline_overlay')) : null,
            'generation_prompt' => filled($this->input('generation_prompt')) ? trim((string) $this->input('generation_prompt')) : null,
        ]);
    }
}
