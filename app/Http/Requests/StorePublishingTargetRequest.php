<?php

namespace App\Http\Requests;

use App\Models\PublishingTarget;
use App\PublishingProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePublishingTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PublishingTarget::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:150', Rule::unique('publishing_targets', 'name')->where(fn ($query) => $query->where('agency_id', $this->input('agency_id')))->ignore($this->targetForUniqueRule())],
            'base_url' => ['required', 'url:http,https', 'max:500', Rule::unique('publishing_targets', 'base_url')->ignore($this->targetForUniqueRule())],
            'protocol' => ['required', Rule::enum(PublishingProtocol::class)],
            'username' => ['required', 'string', 'max:150'],
            'credential' => [$this->targetForUniqueRule() ? 'nullable' : 'required', 'string', 'min:8', 'max:2000'],
            'default_author_id' => ['nullable', 'integer', 'min:1'],
            'default_category_ids' => ['nullable', 'array', 'max:50'],
            'default_category_ids.*' => ['integer', 'min:1', 'distinct'],
            'default_tag_ids' => ['nullable', 'array', 'max:100'],
            'default_tag_ids.*' => ['integer', 'min:1', 'distinct'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $host = mb_strtolower((string) parse_url((string) $this->input('base_url'), PHP_URL_HOST));

            if ($host === 'localhost' || str_ends_with($host, '.local')) {
                $validator->errors()->add('base_url', 'Yerel ağ adresleri yayın hedefi olarak kullanılamaz.');
            }

            if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $validator->errors()->add('base_url', 'Özel veya ayrılmış IP adresleri yayın hedefi olarak kullanılamaz.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'name' => trim((string) $this->input('name')),
            'base_url' => rtrim(Str::lower(trim((string) $this->input('base_url'))), '/'),
            'username' => trim((string) $this->input('username')),
            'credential' => filled($this->input('credential')) ? trim((string) $this->input('credential')) : null,
            'default_author_id' => filled($this->input('default_author_id')) ? (int) $this->input('default_author_id') : null,
            'default_category_ids' => $this->parseIds($this->input('default_category_ids')),
            'default_tag_ids' => $this->parseIds($this->input('default_tag_ids')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    protected function targetForUniqueRule(): ?PublishingTarget
    {
        return null;
    }

    /** @return array<int, int> */
    private function parseIds(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('intval', $values), fn (int $id): bool => $id > 0));
    }
}
