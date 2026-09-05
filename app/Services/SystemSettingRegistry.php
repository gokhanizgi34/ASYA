<?php

namespace App\Services;

use App\SettingValueType;

class SystemSettingRegistry
{
    /**
     * @return array<string, array{
     *     field: string,
     *     group: string,
     *     label: string,
     *     description: string,
     *     type: SettingValueType,
     *     default: string|int|bool,
     *     min?: int,
     *     max?: int,
     *     options?: array<string, string>
     * }>
     */
    public function definitions(): array
    {
        return [
            'app.display_name' => [
                'field' => 'app_display_name',
                'group' => 'Genel',
                'label' => 'Uygulama adı',
                'description' => 'Panel başlıklarında ve bildirimlerde gösterilen ad.',
                'type' => SettingValueType::String,
                'default' => 'ASYA',
            ],
            'app.timezone' => [
                'field' => 'app_timezone',
                'group' => 'Genel',
                'label' => 'Saat dilimi',
                'description' => 'Planlama ve rapor tarihlerinin yorumlanacağı saat dilimi.',
                'type' => SettingValueType::Select,
                'default' => 'Europe/Istanbul',
                'options' => [
                    'Europe/Istanbul' => 'İstanbul (UTC+3)',
                    'UTC' => 'UTC',
                    'Europe/London' => 'Londra',
                    'Europe/Berlin' => 'Berlin',
                    'America/New_York' => 'New York',
                ],
            ],
            'app.locale' => [
                'field' => 'app_locale',
                'group' => 'Genel',
                'label' => 'Arayüz dili',
                'description' => 'Ajans için varsayılan panel dili.',
                'type' => SettingValueType::Select,
                'default' => 'tr',
                'options' => ['tr' => 'Türkçe', 'en' => 'English'],
            ],
            'content.default_language' => [
                'field' => 'content_default_language',
                'group' => 'İçerik',
                'label' => 'Varsayılan içerik dili',
                'description' => 'Yeni haber ve yapay zekâ üretimlerinde başlangıç dili.',
                'type' => SettingValueType::Select,
                'default' => 'tr',
                'options' => ['tr' => 'Türkçe', 'en' => 'English'],
            ],
            'content.raw_news_retention_days' => [
                'field' => 'content_raw_news_retention_days',
                'group' => 'İçerik',
                'label' => 'Ham haber saklama süresi',
                'description' => 'İşlenmiş ham haberlerin saklanacağı gün sayısı.',
                'type' => SettingValueType::Integer,
                'default' => 90,
                'min' => 7,
                'max' => 365,
            ],
            'visual.ai_generation_enabled' => [
                'field' => 'visual_ai_generation_enabled',
                'group' => 'İçerik',
                'label' => 'AI görsel üretimi',
                'description' => 'Kaynak görseli bulunamazsa yapay zekâ ile kapak görseli üretir.',
                'type' => SettingValueType::Boolean,
                'default' => false,
            ],
            'trends.google_daily_item_limit' => [
                'field' => 'trends_google_daily_item_limit',
                'group' => 'İçerik',
                'label' => 'Google Trends günlük kotası',
                'description' => 'Google Trends üzerinden bir ajans için bir günde alınabilecek azami haber sayısı.',
                'type' => SettingValueType::Integer,
                'default' => 10,
                'min' => 0,
                'max' => 100,
            ],
            'ai.max_input_characters' => [
                'field' => 'ai_max_input_characters',
                'group' => 'Yapay zekâ maliyeti',
                'label' => 'AI azami girdi karakteri',
                'description' => 'Ham haberden AI servisine gönderilecek azami metin uzunluğu.',
                'type' => SettingValueType::Integer,
                'default' => 8000,
                'min' => 2000,
                'max' => 30000,
            ],
            'ai.max_output_tokens' => [
                'field' => 'ai_max_output_tokens',
                'group' => 'Yapay zekâ maliyeti',
                'label' => 'AI azami çıktı tokenı',
                'description' => 'Metin üretimlerinde sağlayıcıdan istenecek azami çıktı büyüklüğü.',
                'type' => SettingValueType::Integer,
                'default' => 1400,
                'min' => 500,
                'max' => 4000,
            ],
            'publishing.require_approval' => [
                'field' => 'publishing_require_approval',
                'group' => 'Yayın',
                'label' => 'Yayın öncesi onay',
                'description' => 'İçerikler dış sistemlere gönderilmeden önce onay gerektirir.',
                'type' => SettingValueType::Boolean,
                'default' => true,
            ],
            'publishing.retry_count' => [
                'field' => 'publishing_retry_count',
                'group' => 'Yayın',
                'label' => 'Yayın yeniden deneme sayısı',
                'description' => 'Geçici bağlantı hatalarında uygulanacak azami deneme.',
                'type' => SettingValueType::Integer,
                'default' => 3,
                'min' => 0,
                'max' => 10,
            ],
            'analytics.retention_days' => [
                'field' => 'analytics_retention_days',
                'group' => 'Veri ve bildirim',
                'label' => 'Analitik saklama süresi',
                'description' => 'Günlük analitik özetlerin saklanacağı gün sayısı.',
                'type' => SettingValueType::Integer,
                'default' => 365,
                'min' => 30,
                'max' => 1095,
            ],
            'notifications.error_digest' => [
                'field' => 'notifications_error_digest',
                'group' => 'Veri ve bildirim',
                'label' => 'Hata özeti bildirimi',
                'description' => 'Açık kritik hatalar için periyodik özet oluşturur.',
                'type' => SettingValueType::Boolean,
                'default' => true,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function findByKey(string $key): ?array
    {
        return $this->definitions()[$key] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function findByField(string $field): ?array
    {
        foreach ($this->definitions() as $key => $definition) {
            if ($definition['field'] === $field) {
                return $definition + ['key' => $key];
            }
        }

        return null;
    }
}
