<?php

namespace App\Http\Requests;

use App\Models\AnalyticsSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AnalyticsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AnalyticsSnapshot::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'], 'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')]];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['from', 'to'])) {
                return;
            }
            if (CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) > 366) {
                $validator->errors()->add('to', 'Rapor aralığı en fazla 366 gün olabilir.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;
        $this->merge(['from' => $this->input('from', now()->subDays(29)->toDateString()), 'to' => $this->input('to', now()->toDateString()), 'agency_id' => filled($agencyId) ? (int) $agencyId : null]);
    }
}
