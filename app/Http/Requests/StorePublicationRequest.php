<?php

namespace App\Http\Requests;

use App\ArticleStatus;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\RemotePublicationStatus;
use App\SourceTrustStatus;
use App\VisualAssetStatus;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Publication::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'article_id' => ['required', 'integer', Rule::exists('articles', 'id')->whereNull('deleted_at')],
            'publishing_target_id' => ['required', 'integer', Rule::exists('publishing_targets', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'remote_status' => ['required', Rule::enum(RemotePublicationStatus::class)],
            'remote_author_id' => ['nullable', 'integer', 'min:1'],
            'remote_category_ids' => ['nullable', 'array', 'max:50'],
            'remote_category_ids.*' => ['integer', 'min:1', 'distinct'],
            'remote_tag_ids' => ['nullable', 'array', 'max:100'],
            'remote_tag_ids.*' => ['integer', 'min:1', 'distinct'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'schedule_timezone' => ['required_with:scheduled_for', Rule::in(DateTimeZone::listIdentifiers())],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['agency_id', 'article_id', 'publishing_target_id'])) {
                return;
            }

            $agencyId = $this->integer('agency_id');
            $article = Article::query()->with(['seoAnalysis', 'selectedVisualAsset'])->find($this->integer('article_id'));
            $target = PublishingTarget::query()->find($this->integer('publishing_target_id'));

            if (! $article || $article->agency_id !== $agencyId) {
                $validator->errors()->add('article_id', 'Seçilen haber bu ajansa ait değildir.');
            } elseif ($article->status !== ArticleStatus::Published || $article->source_trust_status !== SourceTrustStatus::Verified) {
                $validator->errors()->add('article_id', 'Yayın için haber yayımlanmış durumda ve kaynağı doğrulanmış olmalıdır.');
            } elseif (! $article->seoAnalysis) {
                $validator->errors()->add('article_id', 'Yayın için haber SEO analizinden geçirilmelidir.');
            } elseif (! $article->selectedVisualAsset || $article->selectedVisualAsset->status !== VisualAssetStatus::Approved || blank($article->selectedVisualAsset->storage_path)) {
                $validator->errors()->add('article_id', 'Yayın için onaylı ve yerel dosyası bulunan bir kapak görseli seçilmelidir.');
            }

            if (! $target || $target->agency_id !== $agencyId) {
                $validator->errors()->add('publishing_target_id', 'Seçilen yayın hedefi bu ajansa ait değildir.');
            }

            if (Publication::query()->where('article_id', $this->integer('article_id'))->where('publishing_target_id', $this->integer('publishing_target_id'))->exists()) {
                $validator->errors()->add('publishing_target_id', 'Bu haber seçilen hedef için daha önce yayın kuyruğuna alınmıştır.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'remote_category_ids' => $this->parseIds($this->input('remote_category_ids')),
            'remote_tag_ids' => $this->parseIds($this->input('remote_tag_ids')),
            'remote_author_id' => filled($this->input('remote_author_id')) ? (int) $this->input('remote_author_id') : null,
            'schedule_timezone' => $this->input('schedule_timezone', config('app.timezone')),
        ]);
    }

    /** @return array<int, int> */
    private function parseIds(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('intval', $values), fn (int $id): bool => $id > 0));
    }
}
