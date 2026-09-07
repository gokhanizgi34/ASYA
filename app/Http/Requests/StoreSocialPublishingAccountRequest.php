<?php

namespace App\Http\Requests;

use App\Models\SocialPublishingAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSocialPublishingAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SocialPublishingAccount::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $account = $this->accountForUniqueRule();

        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:120'],
            'platform' => ['required', Rule::in(['x', 'instagram', 'facebook', 'youtube', 'tiktok', 'linkedin'])],
            'account_handle' => [
                'required',
                'string',
                'max:120',
                Rule::when($this->input('platform') === 'x', ['regex:/^@[A-Za-z0-9_]{1,15}$/']),
                Rule::unique('social_publishing_accounts')->where(fn ($query) => $query->where('agency_id', $this->input('agency_id'))->where('platform', $this->input('platform')))->ignore($account),
            ],
            'access_token' => [$account ? 'nullable' : 'required', 'nullable', 'string', 'min:8', 'max:4000'],
            'api_key' => ['nullable', 'string', 'min:8', 'max:4000'],
            'api_secret' => ['nullable', 'string', 'min:8', 'max:4000'],
            'access_token_secret' => ['nullable', 'string', 'min:8', 'max:4000'],
            'mention_rules_text' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $account = $this->accountForUniqueRule();
            $oauth1Values = collect(['api_key', 'api_secret', 'access_token_secret'])
                ->map(fn (string $field): bool => filled($this->input($field)) || filled($account?->{$field}));

            if ($oauth1Values->contains(true) && $oauth1Values->contains(false)) {
                $validator->errors()->add('api_key', 'OAuth 1.0a kullanılacaksa API Key, API Secret ve Access Token Secret birlikte girilmelidir.');
            }

            $lines = preg_split('/\R/u', (string) $this->input('mention_rules_text')) ?: [];

            if (count(array_filter($lines, fn (string $line): bool => trim($line) !== '')) > 100) {
                $validator->errors()->add('mention_rules_text', 'En fazla 100 belediye etiket kuralı eklenebilir.');

                return;
            }

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line !== '' && preg_match('/^.{2,120}\s*=\s*@[A-Za-z0-9_]{1,15}$/u', $line) !== 1) {
                    $validator->errors()->add('mention_rules_text', 'Her satır “Pendik Belediyesi=@Pendik_Belediye” biçiminde olmalıdır.');

                    return;
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $account = $this->accountForUniqueRule();
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id', $account?->agency_id) : $this->user()?->agency_id;
        $platform = (string) $this->input('platform', $account?->platform);
        $handle = trim((string) $this->input('account_handle', $account?->account_handle));

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'name' => trim((string) $this->input('name', $account?->name)),
            'platform' => $platform,
            'account_handle' => $platform === 'x' && $handle !== '' ? '@'.ltrim($handle, '@') : $handle,
            'access_token' => filled($this->input('access_token')) ? trim((string) $this->input('access_token')) : null,
            'api_key' => filled($this->input('api_key')) ? trim((string) $this->input('api_key')) : null,
            'api_secret' => filled($this->input('api_secret')) ? trim((string) $this->input('api_secret')) : null,
            'access_token_secret' => filled($this->input('access_token_secret')) ? trim((string) $this->input('access_token_secret')) : null,
            'mention_rules_text' => trim((string) $this->input('mention_rules_text')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    protected function accountForUniqueRule(): ?SocialPublishingAccount
    {
        return null;
    }
}
