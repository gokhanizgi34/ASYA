<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use App\SettingValueType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SystemSettings
{
    /** @var array<string, mixed> */
    private array $memoized = [];

    public function __construct(
        private readonly SystemSettingRegistry $registry,
    ) {}

    public function get(string $key, ?int $agencyId = null): string|int|bool
    {
        $cacheKey = ($agencyId === null ? 'system' : 'agency:'.$agencyId).'|'.$key;

        if (array_key_exists($cacheKey, $this->memoized)) {
            return $this->memoized[$cacheKey];
        }

        $definition = $this->registry->findByKey($key);

        if ($definition === null) {
            throw new InvalidArgumentException('Tanımsız sistem ayarı: '.$key);
        }

        $setting = null;

        if ($agencyId !== null) {
            $setting = SystemSetting::query()
                ->where('scope_key', 'agency:'.$agencyId)
                ->where('key', $key)
                ->first();
        }

        $setting ??= SystemSetting::query()
            ->where('scope_key', 'system')
            ->where('key', $key)
            ->first();

        return $this->memoized[$cacheKey] = $setting instanceof SystemSetting
            ? $this->cast($setting->value, $definition['type'])
            : $definition['default'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function resolved(?int $agencyId): array
    {
        $definitions = $this->registry->definitions();
        $keys = array_keys($definitions);
        $systemValues = SystemSetting::query()->where('scope_key', 'system')->whereIn('key', $keys)->get()->keyBy('key');
        $agencyValues = $agencyId === null
            ? collect()
            : SystemSetting::query()->where('scope_key', 'agency:'.$agencyId)->whereIn('key', $keys)->get()->keyBy('key');

        $resolved = [];

        foreach ($definitions as $key => $definition) {
            $agencySetting = $agencyValues->get($key);
            $systemSetting = $systemValues->get($key);
            $selected = $agencySetting ?? $systemSetting;

            $resolved[$definition['field']] = $definition + [
                'key' => $key,
                'value' => $selected instanceof SystemSetting
                    ? $this->cast($selected->value, $definition['type'])
                    : $definition['default'],
                'inherited' => $agencyId !== null && ! $agencySetting instanceof SystemSetting,
                'source' => $agencySetting instanceof SystemSetting ? 'Ajans' : ($systemSetting instanceof SystemSetting ? 'Sistem' : 'Varsayılan'),
            ];
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $inherit
     */
    public function save(?int $agencyId, array $values, array $inherit, User $updatedBy): void
    {
        $scopeKey = $agencyId === null ? 'system' : 'agency:'.$agencyId;

        DB::transaction(function () use ($agencyId, $inherit, $scopeKey, $updatedBy, $values): void {
            foreach ($values as $field => $value) {
                $definition = $this->registry->findByField($field);

                if ($definition === null) {
                    continue;
                }

                $key = $definition['key'];

                if ($agencyId !== null && filter_var($inherit[$field] ?? false, FILTER_VALIDATE_BOOL)) {
                    SystemSetting::query()->where('scope_key', $scopeKey)->where('key', $key)->delete();

                    continue;
                }

                SystemSetting::query()->updateOrCreate(
                    ['scope_key' => $scopeKey, 'key' => $key],
                    [
                        'agency_id' => $agencyId,
                        'updated_by_id' => $updatedBy->getKey(),
                        'value' => $this->serialize($value, $definition['type']),
                        'type' => $definition['type'],
                    ],
                );
            }
        });

        $this->memoized = [];
    }

    private function cast(?string $value, SettingValueType $type): string|int|bool
    {
        return match ($type) {
            SettingValueType::Boolean => filter_var($value, FILTER_VALIDATE_BOOL),
            SettingValueType::Integer => (int) $value,
            SettingValueType::String, SettingValueType::Select => (string) $value,
        };
    }

    private function serialize(mixed $value, SettingValueType $type): string
    {
        return match ($type) {
            SettingValueType::Boolean => filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0',
            SettingValueType::Integer => (string) ((int) $value),
            SettingValueType::String, SettingValueType::Select => (string) $value,
        };
    }
}
