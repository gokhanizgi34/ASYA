<?php

namespace App\Http\Requests;

use App\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agency = $this->route('agency');

        return $agency instanceof Agency && ($this->user()?->can('update', $agency) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $agency = $this->route('agency');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('agencies', 'name')->ignore($agency)],
            'slug' => ['required', 'string', 'max:255', Rule::unique('agencies', 'slug')->ignore($agency)],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subscription_starts_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date', 'after_or_equal:subscription_starts_at'],
            'province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'category_name' => ['nullable', 'string', 'max:150'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => Str::slug((string) $this->input('name')),
            'contact_email' => filled($this->input('contact_email')) ? Str::lower(trim((string) $this->input('contact_email'))) : null,
            'phone' => filled($this->input('phone')) ? trim((string) $this->input('phone')) : null,
            'province' => filled($this->input('province')) ? trim((string) $this->input('province')) : null,
            'district' => filled($this->input('district')) ? trim((string) $this->input('district')) : null,
            'category_name' => filled($this->input('category_name')) ? trim((string) $this->input('category_name')) : null,
        ]);
    }
}
