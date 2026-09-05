<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\ZodiacSign;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class HoroscopeAiWriter
{
    public function __construct(
        private readonly AiIntegrationRegistry $registry,
        private readonly ExternalUrlGuard $urlGuard,
        private readonly SystemSettings $settings,
    ) {}

    /** @return array<string, array{general: string, traits: string, rising: string, love: string, career: string, money: string, health: string, lucky_color: string, lucky_number: int}> */
    public function write(int $agencyId, CarbonInterface $date): array
    {
        $lastError = 'Aktif ve uyumlu bir AI sağlayıcısı bulunamadı.';

        foreach ($this->registry->forAgency($agencyId) as $integration) {
            try {
                $payload = $this->decode($this->request($integration, $this->prompt($date)));

                return $this->validate($payload);
            } catch (Throwable $exception) {
                $lastError = $integration->name.': '.$exception->getMessage();
            }
        }

        throw new RuntimeException('Günlük burç yorumları AI ile üretilemedi. '.$lastError);
    }

    private function request(ApiIntegration $integration, string $prompt): string
    {
        if ($integration->provider === IntegrationProvider::GoogleGemini) {
            $root = preg_replace('~/models(?:\?.*)?$~', '', rtrim($integration->base_url, '/')) ?: '';
            $url = $root.'/models/'.rawurlencode((string) $integration->model).':generateContent';
            $this->urlGuard->assertSafe($url);
            $response = Http::acceptJson()->asJson()
                ->withQueryParameters(['key' => (string) $integration->credential])
                ->connectTimeout(10)->timeout(max(60, $integration->timeout_seconds))
                ->post($url, [
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'maxOutputTokens' => (int) $this->settings->get('ai.max_output_tokens', $integration->agency_id),
                    ],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                ])->throw();

            return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        }

        if (in_array($integration->provider, [IntegrationProvider::OpenAi, IntegrationProvider::DeepSeek, IntegrationProvider::Mistral, IntegrationProvider::XAi, IntegrationProvider::Groq, IntegrationProvider::OpenRouter], true)) {
            $base = rtrim($integration->base_url, '/');
            $url = preg_match('~/models(?:\?.*)?$~', $base) === 1
                ? (string) preg_replace('~/models(?:\?.*)?$~', '/chat/completions', $base)
                : (str_ends_with($base, '/v1') ? $base.'/chat/completions' : $base.'/chat/completions');
            $this->urlGuard->assertSafe($url);
            $response = Http::acceptJson()->asJson()->withToken((string) $integration->credential)
                ->connectTimeout(10)->timeout(max(60, $integration->timeout_seconds))
                ->post($url, [
                    'model' => $integration->model,
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => (int) $this->settings->get('ai.max_output_tokens', $integration->agency_id),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ])->throw();

            return (string) data_get($response->json(), 'choices.0.message.content', '');
        }

        throw new RuntimeException('Sağlayıcı burç üretimi için desteklenmiyor.');
    }

    private function prompt(CarbonInterface $date): string
    {
        $signs = collect(ZodiacSign::cases())->map(fn (ZodiacSign $sign): string => $sign->value.'='.$sign->label())->implode(', ');

        return $date->format('d.m.Y').' tarihi için Türkçe günlük burç yorumları üret. Metinler birbirinden özgün, akıcı ve eğlence amaçlı olsun; kesin sağlık, yatırım veya kader iddiası verme. Her alan 2-3 anlamlı cümle içersin. traits alanında burcun temel özelliklerini, rising alanında yükselen etkisini açıkla. Yalnızca saf JSON döndür. Burçlar: '.$signs.'. Şema: {"forecasts":[{"sign":"aries","general":"...","traits":"...","rising":"...","love":"...","career":"...","money":"...","health":"...","lucky_color":"...","lucky_number":1}]}';
    }

    /** @return array<string, mixed> */
    private function decode(string $content): array
    {
        $clean = Str::of($content)->trim()->replaceMatches('/^```(?:json)?\s*|\s*```$/u', '')->toString();
        $decoded = json_decode($clean, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI geçerli burç JSON verisi döndürmedi.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $payload @return array<string, array{general: string, love: string, career: string, money: string, health: string, lucky_color: string, lucky_number: int}> */
    private function validate(array $payload): array
    {
        $rows = collect($payload['forecasts'] ?? [])->keyBy('sign');
        $result = [];

        foreach (ZodiacSign::cases() as $sign) {
            $row = $rows->get($sign->value);
            if (! is_array($row)) {
                throw new RuntimeException($sign->label().' burcu AI yanıtında bulunamadı.');
            }

            foreach (['general', 'traits', 'rising', 'love', 'career', 'money', 'health'] as $field) {
                if (in_array($field, ['traits', 'rising'], true) && blank($row[$field] ?? null)) {
                    continue;
                }

                if (Str::length(Str::squish((string) ($row[$field] ?? ''))) < 35) {
                    throw new RuntimeException($sign->label().' burcunun '.$field.' alanı yetersiz.');
                }
            }

            $result[$sign->value] = [
                'general' => Str::squish((string) $row['general']),
                'traits' => Str::squish((string) ($row['traits'] ?? 'Bu burcun temel özellikleri günlük yorumla birlikte değerlendirilmelidir.')),
                'rising' => Str::squish((string) ($row['rising'] ?? 'Yükselen burcun etkisi kişisel doğum haritasına göre farklılık gösterebilir.')),
                'love' => Str::squish((string) $row['love']),
                'career' => Str::squish((string) $row['career']),
                'money' => Str::squish((string) $row['money']),
                'health' => Str::squish((string) $row['health']),
                'lucky_color' => Str::of((string) ($row['lucky_color'] ?? 'Mavi'))->squish()->limit(50, '')->toString(),
                'lucky_number' => min(99, max(1, (int) ($row['lucky_number'] ?? 1))),
            ];
        }

        return $result;
    }
}
