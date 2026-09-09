<?php

namespace App\Http\Requests;

use App\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAdminNewsDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSystemAdministrator() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:2000'],
            'body' => ['required', 'string', 'max:200000'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'selection_mode' => ['required', Rule::in(['all', 'province', 'selected'])],
            'province' => ['nullable', 'string', 'max:100', 'required_if:selection_mode,province'],
            'agency_ids' => ['nullable', 'array', 'max:5000', 'required_if:selection_mode,selected'],
            'agency_ids.*' => ['required', 'integer', 'distinct', Rule::exists('agencies', 'id')->where('is_active', true)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $query = Agency::query()->where('is_active', true);

            if ($this->input('selection_mode') === 'province') {
                $query->where('province', $this->input('province'));
            } elseif ($this->input('selection_mode') === 'selected') {
                $query->whereKey((array) $this->input('agency_ids'));
            }

            if (! $query->exists()) {
                $validator->errors()->add('selection_mode', 'Seçiminize uyan aktif bir ajans bulunamadı.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'summary' => trim((string) $this->input('summary')),
            'body' => trim((string) $this->input('body')),
            'province' => filled($this->input('province')) ? trim((string) $this->input('province')) : null,
            'agency_ids' => array_values(array_unique(array_filter(array_map('intval', (array) $this->input('agency_ids', []))))),
        ]);
    }
}
