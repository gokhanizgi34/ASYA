<?php

namespace App\Http\Requests;

use App\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Agency::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('agencies', 'name')],
            'slug' => ['required', 'string', 'max:255', Rule::unique('agencies', 'slug')],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subscription_starts_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date', 'after_or_equal:subscription_starts_at'],
            'province' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'category_name' => ['required', 'string', 'max:150'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => Str::slug((string) $this->input('name')),
            'contact_email' => filled($this->input('contact_email')) ? Str::lower(trim((string) $this->input('contact_email'))) : null,
            'phone' => filled($this->input('phone')) ? trim((string) $this->input('phone')) : null,
            'subscription_starts_at' => filled($this->input('subscription_starts_at')) ? $this->input('subscription_starts_at') : today()->toDateString(),
            'province' => trim((string) $this->input('province')),
            'district' => trim((string) $this->input('district')),
            'category_name' => trim((string) ($this->input('category_name') ?: $this->input('district'))),
            'is_active' => $this->boolean('is_active'),
        ]);

        if (! filled($this->input('subscription_ends_at'))) {
            $this->merge(['subscription_ends_at' => today()->addDays(2)->toDateString()]);
        }
    }
}
