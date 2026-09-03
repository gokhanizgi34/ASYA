<?php

namespace App\Http\Requests;

use App\Models\PublishingTarget;
use App\Models\TaxonomyMapping;
use App\TaxonomyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaxonomyMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TaxonomyMapping::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'publishing_target_id' => ['required', 'integer', Rule::exists('publishing_targets', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(TaxonomyType::class)],
            'source_term' => ['required', 'string', 'max:150'],
            'source_key' => ['required', 'string', 'max:150', Rule::unique('taxonomy_mappings', 'source_key')->where(fn ($query) => $query->where('publishing_target_id', $this->input('publishing_target_id'))->where('type', $this->input('type')))->ignore($this->mappingForUniqueRule())],
            'remote_id' => ['required', 'integer', 'min:1'],
            'remote_name' => ['required', 'string', 'max:150'],
            'priority' => ['required', 'integer', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->hasAny(['agency_id', 'publishing_target_id'])) {
                $target = PublishingTarget::query()->find($this->integer('publishing_target_id'));
                if (! $target || $target->agency_id !== $this->integer('agency_id')) {
                    $validator->errors()->add('publishing_target_id', 'Yayın hedefi seçilen ajansa ait değildir.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;
        $sourceTerm = Str::of((string) $this->input('source_term'))->replaceStart('#', '')->squish()->toString();

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'source_term' => $sourceTerm,
            'source_key' => Str::slug($sourceTerm),
            'remote_name' => Str::squish((string) $this->input('remote_name')),
            'priority' => (int) $this->input('priority', 50),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    protected function mappingForUniqueRule(): ?TaxonomyMapping
    {
        return null;
    }
}
