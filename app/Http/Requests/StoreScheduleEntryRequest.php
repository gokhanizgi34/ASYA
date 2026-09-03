<?php

namespace App\Http\Requests;

use App\CampaignStatus;
use App\Models\Campaign;
use App\Models\Publication;
use App\Models\ScheduleEntry;
use App\PublicationStatus;
use App\ScheduleAction;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreScheduleEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ScheduleEntry::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)], 'action' => ['required', Rule::enum(ScheduleAction::class)], 'publication_id' => ['nullable', 'integer', Rule::exists('publications', 'id')], 'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')->whereNull('deleted_at')], 'scheduled_for' => ['required', 'date', 'after:now'], 'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())]];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['agency_id', 'action', 'publication_id', 'campaign_id'])) {
                return;
            }
            $action = ScheduleAction::from($this->string('action')->toString());
            $agencyId = $this->integer('agency_id');
            if ($action === ScheduleAction::PublishWordPress) {
                if (! $this->filled('publication_id') || $this->filled('campaign_id')) {
                    $validator->errors()->add('publication_id', 'WordPress yayını için yalnızca bir yayın kaydı seçilmelidir.');

                    return;
                }
                $publication = Publication::query()->with('publishingTarget')->find($this->integer('publication_id'));
                if (! $publication || $publication->agency_id !== $agencyId) {
                    $validator->errors()->add('publication_id', 'Seçilen yayın bu ajansa ait değildir.');
                } elseif ($publication->status !== PublicationStatus::Failed || ! $publication->publishingTarget->is_active) {
                    $validator->errors()->add('publication_id', 'Seçilen yayın planlamaya uygun değildir.');
                }
            } else {
                if (! $this->filled('campaign_id') || $this->filled('publication_id')) {
                    $validator->errors()->add('campaign_id', 'Kampanya eylemi için yalnızca bir kampanya seçilmelidir.');

                    return;
                }
                $campaign = Campaign::query()->find($this->integer('campaign_id'));
                if (! $campaign || $campaign->agency_id !== $agencyId) {
                    $validator->errors()->add('campaign_id', 'Seçilen kampanya bu ajansa ait değildir.');
                } elseif ($action === ScheduleAction::ActivateCampaign && $campaign->status !== CampaignStatus::Scheduled) {
                    $validator->errors()->add('campaign_id', 'Yalnızca planlanmış kampanya otomatik başlatılabilir.');
                } elseif ($action === ScheduleAction::CompleteCampaign && ! in_array($campaign->status, [CampaignStatus::Active, CampaignStatus::Paused], true)) {
                    $validator->errors()->add('campaign_id', 'Yalnızca aktif veya duraklatılmış kampanya otomatik tamamlanabilir.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;
        $this->merge(['agency_id' => filled($agencyId) ? (int) $agencyId : null, 'timezone' => $this->input('timezone', config('app.timezone'))]);
    }
}
