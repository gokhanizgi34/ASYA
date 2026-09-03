<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AiVisionNewsExtractor
{
    public function __construct(
        private readonly AiIntegrationRegistry $registry,
        private readonly ExternalUrlGuard $urlGuard,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function extract(int $agencyId, string $imagePath): array
    {
        $image = file_get_contents($imagePath);

        if ($image === false || $image === '') {
            throw new RuntimeException('Haber ekran görüntüsü okunamadı.');
        }

        $dataUrl = 'data:image/png;base64,'.base64_encode($image);
        $lastError = 'Aktif ve görsel okuyabilen yapay zekâ entegrasyonu bulunamadı.';

        foreach ($this->registry->forAgency($agencyId) as $integration) {
            try {
                $text = $this->request($integration, $dataUrl);
                $records = $this->decodeRecords($text);

                if ($records !== []) {
                    return array_slice($records, 0, 20);
                }
            } catch (Throwable $exception) {
                $lastError = $integration->name.': '.$exception->getMessage();
            }
        }

        throw new RuntimeException('Görsel haber okuma tamamlanamadı. '.$lastError);
    }

    private function request(ApiIntegration $integration, string $dataUrl): string
    {
        return match ($integration->provider) {
            IntegrationProvider::Anthropic => $this->anthropic($integration, $dataUrl),
            IntegrationProvider::GoogleGemini => $this->gemini($integration, $dataUrl),
            IntegrationProvider::OpenAi,
            IntegrationProvider::DeepSeek,
            IntegrationProvider::Mistral,
            IntegrationProvider::XAi,
            IntegrationProvider::Groq,
            IntegrationProvider::OpenRouter => $this->openAiCompatible($integration, $dataUrl),
            default => throw new RuntimeException('Bu sağlayıcı görsel haber okuma için desteklenmiyor.'),
        };
    }

    private function openAiCompatible(ApiIntegration $integration, string $dataUrl): string
    {
        $url = preg_replace('~/models(?:\?.*)?$~', '/chat/completions', rtrim($integration->base_url, '/')) ?: '';
        $this->urlGuard->assertSafe($url);
        $response = $this->baseRequest($integration)
            ->withToken((string) $integration->credential)
            ->post($url, [
                'model' => $integration->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->prompt()],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ],
                ]],
            ]);

        $response->throw();

        return $this->contentText(data_get($response->json(), 'choices.0.message.content'));
    }

    private function anthropic(ApiIntegration $integration, string $dataUrl): string
    {
        $url = preg_replace('~/models(?:\?.*)?$~', '/messages', rtrim($integration->base_url, '/')) ?: '';
        $this->urlGuard->assertSafe($url);
        $response = $this->baseRequest($integration)
            ->withHeader('x-api-key', (string) $integration->credential)
            ->withHeader('anthropic-version', '2023-06-01')
            ->post($url, [
                'model' => $integration->model,
                'max_tokens' => 4000,
                'temperature' => 0,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => str($dataUrl)->after(',')->toString()]],
                        ['type' => 'text', 'text' => $this->prompt()],
                    ],
                ]],
            ]);

        $response->throw();

        return $this->contentText(data_get($response->json(), 'content'));
    }

    private function gemini(ApiIntegration $integration, string $dataUrl): string
    {
        $root = preg_replace('~/models(?:\?.*)?$~', '', rtrim($integration->base_url, '/')) ?: '';
        $url = $root.'/models/'.rawurlencode((string) $integration->model).':generateContent';
        $this->urlGuard->assertSafe($url);
        $response = $this->baseRequest($integration)
            ->withQueryParameters(['key' => (string) $integration->credential])
            ->post($url, [
                'generationConfig' => ['temperature' => 0, 'responseMimeType' => 'application/json'],
                'contents' => [[
                    'parts' => [
                        ['text' => $this->prompt()],
                        ['inlineData' => ['mimeType' => 'image/png', 'data' => str($dataUrl)->after(',')->toString()]],
                    ],
                ]],
            ]);

        $response->throw();

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }

    private function baseRequest(ApiIntegration $integration): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(min(8, $integration->timeout_seconds))
            ->timeout(max(30, $integration->timeout_seconds));
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
Bu haber listeleme sayfası ekran görüntüsündeki en güncel haberleri oku. Yalnızca görüntüde açıkça görülen bilgileri kullan; tahmin etme. Saf JSON döndür:
{"articles":[{"title":"başlık","body":"görünen özet veya metin","published_at":"ISO-8601 veya null"}]}
Başlığı olmayanları alma. Gövde alanı en az 20 karakter olsun. Açıklama yoksa görüntüdeki başlığa açıklama uydurma.
PROMPT;
    }

    /** @return array<int, array<string, mixed>> */
    private function decodeRecords(string $text): array
    {
        $text = trim($text);
        $positions = array_filter([
            strpos($text, '{') === false ? PHP_INT_MAX : strpos($text, '{'),
            strpos($text, '[') === false ? PHP_INT_MAX : strpos($text, '['),
        ], fn (int $position): bool => $position !== PHP_INT_MAX);
        $start = $positions === [] ? null : min($positions);
        $decoded = $start === null ? null : json_decode(substr($text, $start), true);

        if (! is_array($decoded)) {
            return [];
        }

        $records = $decoded['articles'] ?? $decoded['items'] ?? $decoded;

        return is_array($records) && array_is_list($records) ? $records : [];
    }

    private function contentText(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            return collect($content)->map(fn (mixed $part): string => is_array($part) ? (string) ($part['text'] ?? '') : '')->implode("\n");
        }

        return '';
    }
}
