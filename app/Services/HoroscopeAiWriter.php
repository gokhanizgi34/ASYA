<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\ZodiacSign;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $errors = [];

        foreach ($this->registry->forAgency($agencyId) as $integration) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $prompt = $this->prompt($date, $attempt === 2);
                    $payload = $this->decode($this->request($integration, $prompt));

                    return $this->validate($payload);
                } catch (Throwable $exception) {
                    $errors[] = $integration->name.' (deneme '.$attempt.'): '.$exception->getMessage();
                    Log::warning('Burç AI üretimi sağlayıcı denemesi başarısız oldu.', [
                        'agency_id' => $agencyId,
                        'provider' => $integration->provider->value,
                        'integration_id' => $integration->id,
                        'attempt' => $attempt,
                        'message' => $exception->getMessage(),
                    ]);

                    if ($exception instanceof RequestException || $attempt === 2) {
                        break;
                    }
                }
            }
        }

        $detail = collect($errors)->take(6)->implode(' | ');

        throw new RuntimeException('Günlük burç yorumları AI ile üretilemedi. '.($detail ?: 'Aktif ve uyumlu bir AI sağlayıcısı bulunamadı.'));
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
                        'responseJsonSchema' => $this->responseSchema(),
                        'temperature' => 0.2,
                        'maxOutputTokens' => max(5000, (int) $this->settings->get('ai.max_output_tokens', $integration->agency_id)),
                    ],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                ])->throw();

            return collect((array) data_get($response->json(), 'candidates.0.content.parts', []))
                ->pluck('text')
                ->filter(fn (mixed $part): bool => is_string($part))
                ->implode("\n");
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
                    'max_tokens' => max(5000, (int) $this->settings->get('ai.max_output_tokens', $integration->agency_id)),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ])->throw();

            return (string) data_get($response->json(), 'choices.0.message.content', '');
        }

        throw new RuntimeException('Sağlayıcı burç üretimi için desteklenmiyor.');
    }

    private function prompt(CarbonInterface $date, bool $isRetry = false): string
    {
        $signs = collect(ZodiacSign::cases())->map(fn (ZodiacSign $sign): string => $sign->value.'='.$sign->label())->implode(', ');

        return ($isRetry ? 'Önceki yanıt biçim veya alan doğrulamasını geçemedi. Bu kez açıklama ve Markdown ekleme. ' : '')
            .$date->format('d.m.Y').' tarihi için Türkçe günlük burç yorumları üret. Metinler birbirinden özgün, akıcı ve eğlence amaçlı olsun; kesin sağlık, yatırım veya kader iddiası verme. general, love, career, money ve health alanlarının her biri 1-2 kısa ama anlamlı cümle olsun. traits ve rising alanlarını tek kısa cümleyle yaz. Tam 12 burcu eksiksiz döndür. Yalnızca saf JSON döndür. Burçlar: '.$signs.'. Şema: {"forecasts":[{"sign":"aries","general":"...","traits":"...","rising":"...","love":"...","career":"...","money":"...","health":"...","lucky_color":"...","lucky_number":1}]}';
    }

    /** @return array<string, mixed> */
    private function decode(string $content): array
    {
        $clean = Str::of($content)->trim()->replaceMatches('/^```(?:json)?\s*|\s*```$/u', '')->toString();
        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        $json = $start === false || $end === false || $end < $start
            ? $clean
            : substr($clean, $start, $end - $start + 1);
        $decoded = json_decode($json, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI geçerli burç JSON verisi döndürmedi: '.json_last_error_msg().'.');
        }

        if (array_is_list($decoded)) {
            return ['forecasts' => $decoded];
        }

        if (isset($decoded['data']['forecasts']) && is_array($decoded['data']['forecasts'])) {
            return ['forecasts' => $decoded['data']['forecasts']];
        }

        return $decoded;
    }

    /** @return array<string, mixed> */
    private function responseSchema(): array
    {
        $text = ['type' => 'string', 'minLength' => 35];

        return [
            'type' => 'object',
            'required' => ['forecasts'],
            'properties' => [
                'forecasts' => [
                    'type' => 'array',
                    'minItems' => 12,
                    'maxItems' => 12,
                    'items' => [
                        'type' => 'object',
                        'required' => ['sign', 'general', 'love', 'career', 'money', 'health', 'lucky_color', 'lucky_number'],
                        'properties' => [
                            'sign' => ['type' => 'string', 'enum' => collect(ZodiacSign::cases())->pluck('value')->all()],
                            'general' => $text,
                            'traits' => ['type' => 'string'],
                            'rising' => ['type' => 'string'],
                            'love' => $text,
                            'career' => $text,
                            'money' => $text,
                            'health' => $text,
                            'lucky_color' => ['type' => 'string'],
                            'lucky_number' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 99],
                        ],
                    ],
                ],
            ],
        ];
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
