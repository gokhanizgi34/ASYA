<?php

namespace App\Http\Requests;

use App\Models\AiPrompt;
use App\Models\ContentBatch;
use App\Models\RawNewsItem;
use App\RawNewsStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContentBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ContentBatch::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:180'],
            'ai_prompt_id' => ['required', 'integer', Rule::exists('ai_prompts', 'id')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'raw_news_ids' => ['required', 'array', 'min:1', 'max:500'],
            'raw_news_ids.*' => ['required', 'integer', 'distinct', Rule::exists('raw_news_items', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['agency_id', 'ai_prompt_id', 'raw_news_ids', 'raw_news_ids.*'])) {
                return;
            }

            $agencyId = $this->integer('agency_id');
            $prompt = AiPrompt::query()->find($this->integer('ai_prompt_id'));

            if ($prompt && $prompt->agency_id !== null && $prompt->agency_id !== $agencyId) {
                $validator->errors()->add('ai_prompt_id', 'Seçilen prompt bu ajans tarafından kullanılamaz.');
            }

            $rawNewsIds = $this->input('raw_news_ids', []);
            $eligibleCount = RawNewsItem::query()
                ->where('agency_id', $agencyId)
                ->whereIn('status', [RawNewsStatus::Pending, RawNewsStatus::Failed])
                ->whereIn('id', $rawNewsIds)
                ->count();

            if ($eligibleCount !== count($rawNewsIds)) {
                $validator->errors()->add('raw_news_ids', 'Tüm ham haberler aynı ajansa ait ve bekleyen veya hatalı durumda olmalıdır.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator()
            ? $this->input('agency_id')
            : $this->user()?->agency_id;
        $rawNewsIds = is_array($this->input('raw_news_ids'))
            ? array_values(array_map('intval', $this->input('raw_news_ids')))
            : $this->input('raw_news_ids');

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'name' => trim((string) $this->input('name')),
            'raw_news_ids' => $rawNewsIds,
        ]);
    }
}
