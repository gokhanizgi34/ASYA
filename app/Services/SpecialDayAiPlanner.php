<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SpecialDayAiPlanner
{
    public function __construct(
        private readonly AiIntegrationRegistry $registry,
        private readonly ExternalUrlGuard $urlGuard,
        private readonly SystemSettings $settings,
    ) {}

    /** @return array<int, array{event_date: string, content_due_at: string, title: string, seo_topics: array<int, string>, ai_provider: string}> */
    public function plan(int $agencyId, int $startYear, int $years): array
    {
        $integrations = $this->registry->forAgency($agencyId);
        if ($integrations->isEmpty()) {
            throw new RuntimeException('Özel gün takvimi için aktif bir yapay zekâ entegrasyonu bulunamadı.');
        }
        $result = [];
        foreach (range($startYear, $startYear + $years - 1) as $year) {
            $lastError = 'Yapay zekâ yanıt vermedi.';
            foreach ($integrations as $integration) {
                try {
                    $result = [...$result, ...$this->validate($this->decode($this->request($integration, $this->prompt($year))), $year, $integration->name)];

                    continue 2;
                } catch (Throwable $exception) {
                    $lastError = $integration->name.': '.$exception->getMessage();
                }
            }
            $result = [...$result, ...$this->fallback($year)];
        }

        return $result;
    }

    private function request(ApiIntegration $integration, string $prompt): string
    {
        if ($integration->provider === IntegrationProvider::GoogleGemini) {
            $root = preg_replace('~/models(?:\?.*)?$~', '', rtrim($integration->base_url, '/')) ?: '';
            $url = $root.'/models/'.rawurlencode((string) $integration->model).':generateContent';
            $this->urlGuard->assertSafe($url);
            $response = Http::acceptJson()->asJson()->withQueryParameters(['key' => (string) $integration->credential])
                ->connectTimeout(10)->timeout(max(90, $integration->timeout_seconds))
                ->post($url, ['generationConfig' => ['responseMimeType' => 'application/json', 'maxOutputTokens' => (int) $this->settings->get('ai.max_output_tokens', $integration->agency_id)], 'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]]])->throw();

            return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        }
        if (in_array($integration->provider, [IntegrationProvider::OpenAi, IntegrationProvider::DeepSeek, IntegrationProvider::Mistral, IntegrationProvider::XAi, IntegrationProvider::Groq, IntegrationProvider::OpenRouter], true)) {
            $base = rtrim($integration->base_url, '/');
            $url = preg_match('~/models(?:\?.*)?$~', $base) === 1 ? (string) preg_replace('~/models(?:\?.*)?$~', '/chat/completions', $base) : $base.'/chat/completions';
            $this->urlGuard->assertSafe($url);
            $response = Http::acceptJson()->asJson()->withToken((string) $integration->credential)
                ->connectTimeout(10)->timeout(max(90, $integration->timeout_seconds))
                ->post($url, ['model' => $integration->model, 'response_format' => ['type' => 'json_object'], 'max_tokens' => (int) $this->settings->get('ai.max_output_tokens', $integration->agency_id), 'messages' => [['role' => 'user', 'content' => $prompt]]])->throw();

            return (string) data_get($response->json(), 'choices.0.message.content', '');
        }
        throw new RuntimeException('Sağlayıcı özel gün planlamasını desteklemiyor.');
    }

    private function prompt(int $year): string
    {
        return $year.' yılı için Türkiye’de haber ve SEO içeriği üretmeye değer resmi, dini, milli ve yaygın uluslararası özel günleri hazırla. Uydurma gün ekleme. Her gün için kullanıcıların gerçekten arayacağı 2-6 özgün haber başlığı öner. Ramazan ve bayram tarihlerini ilgili yılın doğru takvimine göre yaz. İçeriğin etkinlikten kaç gün önce hazırlanacağını 1-45 arasında lead_days alanında belirt. Yalnızca saf JSON döndür. Şema: {"events":[{"date":"'.$year.'-01-01","title":"Yılbaşı","lead_days":14,"seo_topics":["Yılbaşı tatili kaç gün","Yılbaşında ulaşım nasıl olacak"]}]}';
    }

    /** @return array<string, mixed> */
    private function decode(string $content): array
    {
        $decoded = json_decode(trim($content), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('AI geçerli takvim JSON verisi döndürmedi.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $payload @return array<int, array{event_date: string, content_due_at: string, title: string, seo_topics: array<int, string>, ai_provider: string}> */
    private function validate(array $payload, int $year, string $provider): array
    {
        $rows = collect($payload['events'] ?? [])->map(function (mixed $row) use ($year, $provider): ?array {
            if (! is_array($row)) {
                return null;
            }
            try {
                $rawDate = (string) ($row['date'] ?? $row['event_date'] ?? '');
                $eventDate = CarbonImmutable::parse($rawDate);
            } catch (Throwable) {
                return null;
            }
            $title = Str::of((string) ($row['title'] ?? ''))->squish()->limit(180, '')->toString();
            $topics = collect($row['seo_topics'] ?? [])->filter(fn (mixed $topic): bool => is_string($topic))
                ->map(fn (string $topic): string => Str::of($topic)->squish()->limit(180, '')->toString())
                ->filter(fn (string $topic): bool => Str::length($topic) >= 12)->unique()->take(6)->values()->all();
            if ($eventDate->year !== $year || Str::length($title) < 3 || $topics === []) {
                return null;
            }
            $leadDays = min(45, max(1, (int) ($row['lead_days'] ?? 14)));

            return ['event_date' => $eventDate->toDateString(), 'content_due_at' => $eventDate->subDays($leadDays)->toDateString(), 'title' => $title, 'seo_topics' => $topics, 'ai_provider' => $provider];
        })->filter()->unique(fn (array $row): string => $row['event_date'].'|'.Str::lower($row['title']))->values()->all();
        if ($rows === []) {
            throw new RuntimeException($year.' için geçerli özel gün bulunamadı.');
        }

        return $rows;
    }

    /** @return array<int, array{event_date: string, content_due_at: string, title: string, seo_topics: array<int, string>, ai_provider: string}> */
    private function fallback(int $year): array
    {
        $events = [
            ['01-01', 'Yılbaşı'],
            ['04-23', 'Ulusal Egemenlik ve Çocuk Bayramı'],
            ['05-01', 'Emek ve Dayanışma Günü'],
            ['05-19', 'Atatürk\'ü Anma, Gençlik ve Spor Bayramı'],
            ['07-15', 'Demokrasi ve Millî Birlik Günü'],
            ['08-30', 'Zafer Bayramı'],
            ['10-29', 'Cumhuriyet Bayramı'],
        ];

        return collect($events)->map(function (array $event) use ($year): array {
            $date = CarbonImmutable::create($year, (int) substr($event[0], 0, 2), (int) substr($event[0], 3, 2));

            return [
                'event_date' => $date->toDateString(),
                'content_due_at' => $date->subDays(14)->toDateString(),
                'title' => $event[1],
                'seo_topics' => [$event[1].' ne zaman', $year.' '.$event[1]],
                'ai_provider' => 'Yerel resmi takvim',
            ];
        })->all();
    }
}
