<?php

namespace App\Http\Requests;

use App\Models\SocialPublishingAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialPublishingAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SocialPublishingAccount::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:120'],
            'platform' => ['required', Rule::in(['x', 'instagram', 'facebook', 'youtube', 'tiktok', 'linkedin'])],
            'account_handle' => ['required', 'string', 'max:120', Rule::unique('social_publishing_accounts')->where(fn ($query) => $query->where('agency_id', $this->input('agency_id'))->where('platform', $this->input('platform')))],
            'access_token' => ['required', 'string', 'min:8', 'max:4000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'name' => trim((string) $this->input('name')),
            'account_handle' => trim((string) $this->input('account_handle')),
            'access_token' => trim((string) $this->input('access_token')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
