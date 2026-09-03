<?php

namespace App\Http\Requests;

use App\CampaignChannel;
use App\CampaignStatus;
use App\Models\Article;
use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Campaign::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:180', Rule::unique('campaigns', 'name')->where(fn ($query) => $query->where('agency_id', $this->input('agency_id')))->ignore($this->campaignForUniqueRule())],
            'objective' => ['required', 'string', 'max:5000'],
            'target_audience' => ['required', 'string', 'max:5000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::enum(CampaignChannel::class), 'distinct'],
            'brief' => ['nullable', 'string', 'max:10000'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'kpi_reach' => ['nullable', 'integer', 'min:0'],
            'kpi_clicks' => ['nullable', 'integer', 'min:0'],
            'kpi_conversions' => ['nullable', 'integer', 'min:0'],
            'contents' => ['nullable', 'array', 'max:50'],
            'contents.*.article_id' => ['nullable', 'integer', Rule::exists('articles', 'id')->whereNull('deleted_at')],
            'contents.*.channel' => ['required_with:contents', Rule::enum(CampaignChannel::class)],
            'contents.*.title' => ['required_with:contents', 'string', 'max:220'],
            'contents.*.body' => ['required_with:contents', 'string', 'max:20000'],
            'contents.*.call_to_action' => ['nullable', 'string', 'max:180'],
            'contents.*.destination_url' => ['nullable', 'url:http,https', 'max:1000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['agency_id', 'channels', 'contents'])) {
                return;
            }
            $agencyId = $this->integer('agency_id');
            $channels = $this->input('channels', []);
            foreach ($this->input('contents', []) as $index => $content) {
                if (! in_array($content['channel'] ?? null, $channels, true)) {
                    $validator->errors()->add("contents.$index.channel", 'İçerik kanalı kampanyanın seçili kanalları arasında olmalıdır.');
                }
                if (filled($content['article_id'] ?? null) && ! Article::query()->whereKey($content['article_id'])->where('agency_id', $agencyId)->exists()) {
                    $validator->errors()->add("contents.$index.article_id", 'Seçilen haber bu ajansa ait değildir.');
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function campaignAttributes(): array
    {
        return [
            ...$this->safe()->only(['agency_id', 'name', 'objective', 'target_audience', 'channels', 'brief', 'budget', 'starts_at', 'ends_at']),
            'status' => $this->campaignForUniqueRule()?->status ?? CampaignStatus::Draft,
            'kpis' => array_filter(['reach' => $this->validated('kpi_reach'), 'clicks' => $this->validated('kpi_clicks'), 'conversions' => $this->validated('kpi_conversions')], fn (mixed $value): bool => $value !== null),
        ];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;
        $this->merge(['agency_id' => filled($agencyId) ? (int) $agencyId : null, 'name' => trim((string) $this->input('name')), 'channels' => array_values(array_unique((array) $this->input('channels', [])))]);
    }

    protected function campaignForUniqueRule(): ?Campaign
    {
        return null;
    }
}
