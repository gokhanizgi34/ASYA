<?php

namespace App\Http\Requests;

use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Services\ExternalUrlGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StoreApiIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ApiIntegration::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $provider = IntegrationProvider::tryFrom((string) $this->input('provider'));
        $credentialRequired = ($provider?->usesSimpleSetup() === true || $this->input('auth_type') !== IntegrationAuthType::None->value)
            && ! $this->integrationForUniqueRule();

        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:150', Rule::unique('api_integrations', 'name')->where(fn ($query) => $query->where('agency_id', $this->input('agency_id')))->withoutTrashed()->ignore($this->integrationForUniqueRule())],
            'provider' => ['required', Rule::enum(IntegrationProvider::class)],
            'model' => [
                Rule::requiredIf($provider?->isAi() === true || $provider === IntegrationProvider::GoogleSearchConsole),
                'nullable',
                ...($provider === IntegrationProvider::GoogleSearchConsole ? ['url:http,https', 'max:1000'] : ['string', 'max:150']),
            ],
            'priority' => ['required', 'integer', 'between:1,100'],
            'is_default' => ['required', 'boolean'],
            'visual_enabled' => ['required', 'boolean'],
            'base_url' => ['required', 'url:http,https', 'max:1000'],
            'auth_type' => ['required', Rule::enum(IntegrationAuthType::class)],
            'username' => ['nullable', 'string', 'max:1000', Rule::requiredIf($this->input('auth_type') === IntegrationAuthType::Basic->value || $provider === IntegrationProvider::GoogleSearchConsole)],
            'api_key_header' => ['nullable', 'string', 'regex:/^[A-Za-z][A-Za-z0-9-]{0,99}$/', Rule::requiredIf($this->input('auth_type') === IntegrationAuthType::ApiKeyHeader->value)],
            'credential' => [$credentialRequired ? 'required' : 'nullable', 'string', 'max:10000'],
            'timeout_seconds' => ['required', 'integer', 'min:2', 'max:60'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'agency_id.required' => 'Ajans seçilmelidir.',
            'provider.required' => 'Yapay zekâ sağlayıcısı seçilmelidir.',
            'credential.required' => 'API anahtarı zorunludur.',
            'credential.max' => 'Kimlik doğrulama bilgisi çok uzun.',
            'model.required' => 'Search Console için haber site haritası adresi zorunludur.',
            'username.required' => 'Search Console mülk adresi zorunludur.',
            'name.unique' => 'Bu yapay zekâ sağlayıcısı seçilen ajans için zaten eklenmiş.',
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('base_url')) {
                try {
                    app(ExternalUrlGuard::class)->assertSafe((string) $this->input('base_url'), false);
                } catch (InvalidArgumentException $exception) {
                    $validator->errors()->add('base_url', $exception->getMessage());
                }
            }

            if (IntegrationProvider::tryFrom((string) $this->input('provider')) === IntegrationProvider::GoogleSearchConsole) {
                $property = trim((string) $this->input('username'));
                $sitemap = trim((string) $this->input('model'));
                $propertyIsDomain = preg_match('/^sc-domain:[a-z0-9.-]+\.[a-z]{2,}$/i', $property) === 1;
                $propertyIsUrl = filter_var($property, FILTER_VALIDATE_URL) !== false
                    && in_array(strtolower((string) parse_url($property, PHP_URL_SCHEME)), ['http', 'https'], true);

                if (! $propertyIsDomain && ! $propertyIsUrl) {
                    $validator->errors()->add('username', 'Search Console mülkü sc-domain:alanadi.com veya tam HTTPS adresi olmalıdır.');
                }

                $sitemapHost = strtolower((string) parse_url($sitemap, PHP_URL_HOST));
                $propertyCoversSitemap = $propertyIsDomain
                    ? ($sitemapHost === strtolower(substr($property, 10)) || str_ends_with($sitemapHost, '.'.strtolower(substr($property, 10))))
                    : ($propertyIsUrl && str_starts_with(rtrim($sitemap, '/').'/', rtrim($property, '/').'/'));

                if ($sitemapHost !== '' && ! $propertyCoversSitemap) {
                    $validator->errors()->add('model', 'Haber site haritası tanımlı Search Console mülkünün kapsamında olmalıdır.');
                }

                if (filled($this->input('credential'))) {
                    $credentials = json_decode((string) $this->input('credential'), true);
                    if (! is_array($credentials)
                        || ($credentials['type'] ?? null) !== 'service_account'
                        || ! is_string($credentials['client_email'] ?? null)
                        || ! is_string($credentials['private_key'] ?? null)
                        || $credentials['client_email'] === ''
                        || $credentials['private_key'] === '') {
                        $validator->errors()->add('credential', 'Geçerli bir Google hizmet hesabı JSON içeriği girilmelidir.');
                    }
                }
            }

            $header = strtolower((string) $this->input('api_key_header'));

            if (in_array($header, ['host', 'content-length', 'cookie', 'set-cookie'], true)) {
                $validator->errors()->add('api_key_header', 'Bu HTTP başlığı güvenlik nedeniyle kullanılamaz.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;
        $provider = IntegrationProvider::tryFrom((string) $this->input('provider'));
        $integration = $this->integrationForUniqueRule();
        $isAiProvider = $provider?->isAi() === true;
        $isSearchConsole = $provider === IntegrationProvider::GoogleSearchConsole;
        $isManagedProvider = $provider?->usesSimpleSetup() === true;
        $providerWasChanged = $integration && $integration->provider !== $provider;

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'name' => $isManagedProvider ? $provider->label() : trim((string) $this->input('name')),
            'model' => $isAiProvider
                ? ($providerWasChanged ? ($provider->suggestedModels()[0] ?? null) : ($integration?->model ?? $provider->suggestedModels()[0] ?? null))
                : (($isSearchConsole || filled($this->input('model'))) ? trim((string) $this->input('model')) : null),
            'priority' => $isManagedProvider ? ($integration?->priority ?? 50) : (int) $this->input('priority', 50),
            'is_default' => $isAiProvider ? ($integration?->is_default ?? false) : $this->boolean('is_default'),
            'visual_enabled' => $provider === IntegrationProvider::Pixabay || $this->boolean('visual_enabled'),
            'base_url' => rtrim(trim((string) ($isManagedProvider ? $provider->defaultBaseUrl() : $this->input('base_url'))), '/'),
            'auth_type' => $isManagedProvider ? $provider->defaultAuthType()->value : $this->input('auth_type'),
            'username' => $isSearchConsole
                ? trim((string) $this->input('username'))
                : ($isManagedProvider ? null : (filled($this->input('username')) ? trim((string) $this->input('username')) : null)),
            'api_key_header' => $isManagedProvider ? $provider->defaultApiKeyHeader() : (filled($this->input('api_key_header')) ? trim((string) $this->input('api_key_header')) : null),
            'credential' => filled($this->input('credential')) ? trim((string) $this->input('credential')) : null,
            'timeout_seconds' => $isManagedProvider ? 15 : (int) $this->input('timeout_seconds', 15),
            'is_active' => $isManagedProvider ? true : $this->boolean('is_active'),
        ]);
    }

    protected function integrationForUniqueRule(): ?ApiIntegration
    {
        return null;
    }
}
