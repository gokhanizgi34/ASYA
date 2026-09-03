<?php

namespace App\Http\Requests;

use App\MailTransportScheme;
use App\Models\AgencyMailSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgencyMailSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AgencyMailSetting::class) === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->isAgencyOwner()) {
            $this->merge(['agency_id' => $this->user()->agency_id]);
        }
    }

    public function rules(): array
    {
        return [
            'agency_id' => ['nullable', 'integer', Rule::exists('agencies', 'id')],
            'host' => ['required', 'string', 'max:255', 'regex:/^(?!https?:\/\/)[a-z0-9.-]+$/i'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'scheme' => ['required', Rule::enum(MailTransportScheme::class)],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:4000'],
            'from_address' => ['required', 'email:rfc', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'notification_email' => ['required', 'email:rfc', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
