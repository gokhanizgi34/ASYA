<?php

namespace App\Http\Requests;

use App\BlacklistAction;
use App\BlacklistRuleType;
use App\Models\BlacklistRule;
use App\Services\BlacklistMatcher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBlacklistRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BlacklistRule::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true)],
            'type' => ['required', Rule::enum(BlacklistRuleType::class)],
            'pattern' => ['required', 'string', 'min:2', 'max:1000'],
            'normalized_pattern' => [
                'required',
                'string',
                'max:1000',
                Rule::unique('blacklist_rules', 'normalized_pattern')
                    ->where(fn ($query) => $query->where('agency_id', $this->input('agency_id'))->where('type', $this->input('type')))
                    ->ignore($this->ruleForUniqueValidation()),
            ],
            'action' => ['required', Rule::enum(BlacklistAction::class)],
            'reason' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['type', 'pattern'])) {
                return;
            }

            $type = BlacklistRuleType::tryFrom((string) $this->input('type'));
            $pattern = (string) $this->input('pattern');

            if ($type === BlacklistRuleType::Domain) {
                $host = app(BlacklistMatcher::class)->normalize(BlacklistRuleType::Domain, $pattern);
                if (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    $validator->errors()->add('pattern', 'Geçerli bir alan adı girin.');
                }
            }

            if ($type === BlacklistRuleType::UrlPrefix) {
                $scheme = parse_url($pattern, PHP_URL_SCHEME);
                $host = parse_url($pattern, PHP_URL_HOST);
                $hasCredentials = parse_url($pattern, PHP_URL_USER) !== null || parse_url($pattern, PHP_URL_PASS) !== null;
                if (! in_array(Str::lower((string) $scheme), ['http', 'https'], true) || ! is_string($host) || $hasCredentials) {
                    $validator->errors()->add('pattern', 'Kimlik bilgisi içermeyen geçerli bir HTTP veya HTTPS adresi girin.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $agencyId = $this->user()?->isSystemAdministrator() ? $this->input('agency_id') : $this->user()?->agency_id;
        $type = BlacklistRuleType::tryFrom((string) $this->input('type'));
        $pattern = trim((string) $this->input('pattern'));

        $this->merge([
            'agency_id' => filled($agencyId) ? (int) $agencyId : null,
            'pattern' => $pattern,
            'normalized_pattern' => $type ? app(BlacklistMatcher::class)->normalize($type, $pattern) : $pattern,
            'reason' => filled($this->input('reason')) ? trim((string) $this->input('reason')) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    protected function ruleForUniqueValidation(): ?BlacklistRule
    {
        return null;
    }
}
