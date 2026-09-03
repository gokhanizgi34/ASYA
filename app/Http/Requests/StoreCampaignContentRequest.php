<?php

namespace App\Http\Requests;

use App\CampaignChannel;
use App\Models\Article;
use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCampaignContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('campaign');

        return $campaign instanceof Campaign && ($this->user()?->can('update', $campaign) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['article_id' => ['nullable', 'integer', Rule::exists('articles', 'id')->whereNull('deleted_at')], 'channel' => ['required', Rule::enum(CampaignChannel::class)], 'title' => ['required', 'string', 'max:220'], 'body' => ['required', 'string', 'max:20000'], 'call_to_action' => ['nullable', 'string', 'max:180'], 'destination_url' => ['nullable', 'url:http,https', 'max:1000']];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $campaign = $this->route('campaign');
            if (! $campaign instanceof Campaign) {
                return;
            }
            if (! in_array($this->input('channel'), $campaign->channels, true)) {
                $validator->errors()->add('channel', 'İçerik kanalı kampanyanın seçili kanalları arasında olmalıdır.');
            }
            if ($this->filled('article_id') && ! Article::query()->whereKey($this->integer('article_id'))->where('agency_id', $campaign->agency_id)->exists()) {
                $validator->errors()->add('article_id', 'Seçilen haber bu ajansa ait değildir.');
            }
        }];
    }
}
