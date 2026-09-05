<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
        $dataUrl = $this->imageDataUrl($imagePath);

        foreach ($this->registry->forAgency($agencyId) as $integration) {
            try {
                $records = $this->decodeRecords($this->request($integration, $this->listingPrompt(), $dataUrl));
                if ($records !== []) {
                    return $records;
                }
            } catch (Throwable) {
            }
        }

        throw new RuntimeException('Görselde okunabilir haber bilgisi bulunamadı.');
    }

    /** @return array{title:string,summary:string,body:string,focus_keyword:string,keywords:array<int,string>,hashtags:array<int,string>,category:string,ai_provider:string} */
    public function generateTopicArticle(int $agencyId, string $topic, ?string $imagePath = null): array
    {
        $dataUrl = $imagePath !== null ? $this->imageDataUrl($imagePath) : null;
        $lastError = 'Yapay zekâ geçerli haber metni döndürmedi.';

        foreach ($this->registry->forAgency($agencyId) as $integration) {
            try {
                $decoded = $this->decodeObject($this->request($integration, $this->topicPrompt($topic, $dataUrl !== null), $dataUrl));
                $title = Str::of(strip_tags((string) ($decoded['title'] ?? '')))->squish()->limit(220, '')->toString();
                $summary = Str::of(strip_tags((string) ($decoded['summary'] ?? '')))->squish()->limit(320, '')->toString();
                $body = trim(strip_tags((string) ($decoded['body'] ?? '')));

                if (Str::length($title) < 20 || Str::length($body) < 500 || count(preg_split('/\R{2,}/u', $body) ?: []) < 5) {
                    throw new RuntimeException('AI çıktısı tam haber uzunluğu ve paragraf yapısını karşılamıyor.');
                }

                return [
                    'title' => $title,
                    'summary' => $summary !== '' ? $summary : Str::limit($body, 155, ''),
                    'body' => $body,
                    'focus_keyword' => Str::of((string) ($decoded['focus_keyword'] ?? $topic))->squish()->limit(120, '')->toString(),
                    'keywords' => $this->stringList($decoded['keywords'] ?? [], 10),
                    'hashtags' => collect($this->stringList($decoded['hashtags'] ?? [], 5))->map(fn (string $tag): string => '#'.Str::studly(Str::replaceStart('#', '', $tag)))->all(),
                    'category' => Str::of((string) ($decoded['category'] ?? 'Gündem'))->squish()->limit(80, '')->toString(),
                    'ai_provider' => $integration->provider->value,
                ];
            } catch (Throwable $exception) {
                $lastError = $integration->name.': '.$exception->getMessage();
            }
        }

        throw new RuntimeException('Konudan haber üretimi tamamlanamadı. '.$lastError);
    }

    private function request(ApiIntegration $integration, string $prompt, ?string $dataUrl): string
    {
        return match ($integration->provider) {
            IntegrationProvider::Anthropic => $this->anthropic($integration, $prompt, $dataUrl),
            IntegrationProvider::GoogleGemini => $this->gemini($integration, $prompt, $dataUrl),
            IntegrationProvider::OpenAi,
            IntegrationProvider::DeepSeek,
            IntegrationProvider::Mistral,
            IntegrationProvider::XAi,
            IntegrationProvider::Groq,
            IntegrationProvider::OpenRouter => $this->openAiCompatible($integration, $prompt, $dataUrl),
            default => throw new RuntimeException('Bu sağlayıcı görsel haber okuma için desteklenmiyor.'),
        };
    }

    private function openAiCompatible(ApiIntegration $integration, string $prompt, ?string $dataUrl): string
    {
        $url = preg_replace('~/models(?:\?.*)?$~', '/chat/completions', rtrim($integration->base_url, '/')) ?: '';
        $this->urlGuard->assertSafe($url);
        $response = $this->baseRequest($integration)
            ->withToken((string) $integration->credential)
            ->post($url, [
                'model' => $integration->model,
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 4000,
                'messages' => [['role' => 'user', 'content' => $dataUrl === null ? $prompt : [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                ]]],
            ]);

        $response->throw();

        return $this->contentText(data_get($response->json(), 'choices.0.message.content'));
    }

    private function anthropic(ApiIntegration $integration, string $prompt, ?string $dataUrl): string
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
                    'content' => array_values(array_filter([
                        $dataUrl === null ? null : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => Str::between($dataUrl, 'data:', ';base64'), 'data' => str($dataUrl)->after(',')->toString()]],
                        ['type' => 'text', 'text' => $prompt],
                    ])),
                ]],
            ]);

        $response->throw();

        return $this->contentText(data_get($response->json(), 'content'));
    }

    private function gemini(ApiIntegration $integration, string $prompt, ?string $dataUrl): string
    {
        $root = preg_replace('~/models(?:\?.*)?$~', '', rtrim($integration->base_url, '/')) ?: '';
        $url = $root.'/models/'.rawurlencode((string) $integration->model).':generateContent';
        $this->urlGuard->assertSafe($url);
        $response = $this->baseRequest($integration)
            ->withQueryParameters(['key' => (string) $integration->credential])
            ->post($url, [
                'generationConfig' => ['temperature' => 0, 'responseMimeType' => 'application/json'],
                'contents' => [[
                    'parts' => array_values(array_filter([
                        ['text' => $prompt],
                        $dataUrl === null ? null : ['inlineData' => ['mimeType' => Str::between($dataUrl, 'data:', ';base64'), 'data' => str($dataUrl)->after(',')->toString()]],
                    ])),
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

    private function listingPrompt(): string
    {
        return <<<'PROMPT'
Bu haber listeleme sayfası ekran görüntüsündeki en güncel haberleri oku. Yalnızca görüntüde açıkça görülen bilgileri kullan; tahmin etme. Saf JSON döndür:
{"articles":[{"title":"başlık","body":"görünen özet veya metin","published_at":"ISO-8601 veya null"}]}
Başlığı olmayanları alma. Gövde alanı en az 20 karakter olsun. Açıklama yoksa görüntüdeki başlığa açıklama uydurma.
PROMPT;
    }

    private function topicPrompt(string $topic, bool $hasImage): string
    {
        return 'Konu: '.$topic."\n\n".($hasImage ? 'Ekli görselde açıkça görülen metinleri, kişi/kurumları ve bağlamı haber için doğrulanabilir girdi olarak kullan. Görselde bulunmayan ayrıntıyı uydurma. ' : '').<<<'PROMPT'
Bu konu hakkında özgün, güncel Türkçe haber hazırla. Konu bir soruysa doğrulanmamış tarih veya sonuç uydurma; bilinen süreci açıkla ve kesinleşmemiş bilgiyi kesinmiş gibi yazma. Başlıkta “gündem oldu”, “ifadesi”, “sosyal medyada” gibi meta anlatım kullanma; doğrudan gerçek konuyu anlat. En az 6 anlamlı paragraf yaz. Kaynak adı, URL, dipnot veya hazırlama açıklaması ekleme. Yalnız saf JSON döndür:
{"title":"30-70 karakter başlık","summary":"120-160 karakter özet","body":"en az 6 paragraf haber metni","focus_keyword":"odak sorgu","keywords":["6-10 kelime"],"hashtags":["#EnFazla5"],"category":"kategori"}
PROMPT;
    }

    private function imageDataUrl(string $imagePath): string
    {
        $bytes = is_file($imagePath) ? file_get_contents($imagePath) : false;
        $mime = $bytes === false ? false : (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if ($bytes === false || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Görsel JPEG, PNG veya WebP biçiminde olmalıdır.');
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
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

    /** @return array<string, mixed> */
    private function decodeObject(string $text): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        $decoded = $start === false || $end === false ? null : json_decode(substr($text, $start, $end - $start + 1), true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI yanıtı geçerli haber JSON verisi içermiyor.');
        }

        return $decoded;
    }

    /** @return array<int, string> */
    private function stringList(mixed $value, int $limit): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,;\n]+/u', $value) ?: [];
        }

        return collect(is_array($value) ? $value : [])
            ->filter(fn (mixed $item): bool => is_scalar($item))
            ->map(fn (mixed $item): string => Str::of(strip_tags((string) $item))->squish()->limit(120, '')->toString())
            ->filter()
            ->unique(fn (string $item): string => Str::lower($item))
            ->take($limit)
            ->values()
            ->all();
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
