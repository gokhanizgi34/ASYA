<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Models\RawNewsItem;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AiNewsWriter
{
    public function __construct(
        private readonly AiIntegrationRegistry $registry,
        private readonly ExternalUrlGuard $urlGuard,
        private readonly NewsContentQualityGate $qualityGate,
        private readonly SystemSettings $settings,
    ) {}

    public function hasActiveIntegration(int $agencyId): bool
    {
        return $this->registry->forAgency($agencyId)->isNotEmpty();
    }

    /**
     * @param  array<string, mixed>  $promptSnapshot
     * @return array{title: string, summary: string, body: string, focus_keyword: string, keywords: array<int, string>, hashtags: array<int, string>, category: string, ai_provider: string}
     */
    public function write(RawNewsItem $rawNewsItem, array $promptSnapshot): array
    {
        $integrations = $this->registry->forAgency($rawNewsItem->agency_id);

        if ($integrations->isEmpty()) {
            throw new RuntimeException('Bu ajans için aktif bir yapay zekâ API entegrasyonu bulunamadı.');
        }

        $lastError = 'Yapay zekâ sağlayıcısı geçerli bir haber metni döndürmedi.';

        foreach ($integrations as $integration) {
            try {
                $content = $this->normalize($this->request($integration, $rawNewsItem, $promptSnapshot));
                $this->qualityGate->assertGenerated($rawNewsItem, $content);

                return [
                    ...$content,
                    'ai_provider' => $integration->provider->value,
                ];
            } catch (Throwable $exception) {
                $lastError = $integration->name.': '.$exception->getMessage();
            }
        }

        throw new RuntimeException('AI haber üretimi tamamlanamadı. '.$lastError);
    }

    /** @param array<string, mixed> $promptSnapshot */
    private function request(ApiIntegration $integration, RawNewsItem $rawNewsItem, array $promptSnapshot): string
    {
        return match ($integration->provider) {
            IntegrationProvider::Anthropic => $this->anthropic($integration, $rawNewsItem, $promptSnapshot),
            IntegrationProvider::GoogleGemini => $this->gemini($integration, $rawNewsItem, $promptSnapshot),
            IntegrationProvider::OpenAi,
            IntegrationProvider::DeepSeek,
            IntegrationProvider::Mistral,
            IntegrationProvider::XAi,
            IntegrationProvider::Groq,
            IntegrationProvider::OpenRouter => $this->openAiCompatible($integration, $rawNewsItem, $promptSnapshot),
            IntegrationProvider::GitHubModels => throw new RuntimeException('GitHub Models 30.07.2026 tarihinde emekliye ayrıldı ve artık kullanılamaz.'),
            default => throw new RuntimeException('Bu sağlayıcı AI haber üretimi için desteklenmiyor.'),
        };
    }

    /** @param array<string, mixed> $promptSnapshot */
    private function openAiCompatible(ApiIntegration $integration, RawNewsItem $rawNewsItem, array $promptSnapshot): string
    {
        $url = $this->endpoint($integration->base_url, 'chat/completions');
        $this->urlGuard->assertSafe($url);
        $response = $this->baseRequest($integration)
            ->withToken((string) $integration->credential)
            ->post($url, [
                'model' => $integration->model,
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => $this->maxOutputTokens($rawNewsItem->agency_id),
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($promptSnapshot)],
                    ['role' => 'user', 'content' => $this->userPrompt($rawNewsItem, $promptSnapshot)],
                ],
            ]);

        $response->throw();

        return $this->contentText(data_get($response->json(), 'choices.0.message.content'));
    }

    /** @param array<string, mixed> $promptSnapshot */
    private function anthropic(ApiIntegration $integration, RawNewsItem $rawNewsItem, array $promptSnapshot): string
    {
        $url = $this->endpoint($integration->base_url, 'messages');
        $this->urlGuard->assertSafe($url);
        $response = $this->baseRequest($integration)
            ->withHeader('x-api-key', (string) $integration->credential)
            ->withHeader('anthropic-version', '2023-06-01')
            ->post($url, [
                'model' => $integration->model,
                'max_tokens' => $this->maxOutputTokens($rawNewsItem->agency_id),
                'system' => $this->systemPrompt($promptSnapshot),
                'messages' => [['role' => 'user', 'content' => $this->userPrompt($rawNewsItem, $promptSnapshot)]],
            ]);

        $response->throw();

        return $this->contentText(data_get($response->json(), 'content'));
    }

    /** @param array<string, mixed> $promptSnapshot */
    private function gemini(ApiIntegration $integration, RawNewsItem $rawNewsItem, array $promptSnapshot): string
    {
        $root = preg_replace('~/models(?:\?.*)?$~', '', rtrim($integration->base_url, '/')) ?: '';
        $url = $root.'/models/'.rawurlencode((string) $integration->model).':generateContent';
        $this->urlGuard->assertSafe($url);
        $response = $this->baseRequest($integration)
            ->withQueryParameters(['key' => (string) $integration->credential])
            ->post($url, [
                'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.2, 'maxOutputTokens' => $this->maxOutputTokens($rawNewsItem->agency_id)],
                'systemInstruction' => ['parts' => [['text' => $this->systemPrompt($promptSnapshot)]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $this->userPrompt($rawNewsItem, $promptSnapshot)]]]],
            ]);

        $response->throw();

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }

    private function baseRequest(ApiIntegration $integration): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(min(10, $integration->timeout_seconds))
            ->timeout(max(60, $integration->timeout_seconds));
    }

    private function endpoint(string $baseUrl, string $endpoint): string
    {
        $url = rtrim($baseUrl, '/');

        if (preg_match('~/models(?:\?.*)?$~', $url) === 1) {
            return (string) preg_replace('~/models(?:\?.*)?$~', '/'.$endpoint, $url);
        }

        if (str_ends_with($url, '/v1')) {
            return $url.'/'.$endpoint;
        }

        return str_ends_with($url, '/'.$endpoint) ? $url : $url.'/'.$endpoint;
    }

    /** @param array<string, mixed> $promptSnapshot */
    private function systemPrompt(array $promptSnapshot): string
    {
        $configuredPrompt = trim((string) ($promptSnapshot['system_prompt'] ?? ''));

        return trim($configuredPrompt."\n\n".<<<'PROMPT'
Aşağıdaki kurallar, kayıtlı özel prompt dahil diğer tüm editoryal talimatlardan üstündür. Sen deneyimli bir Türkçe kurumsal haber ajansı editörüsün. İHA, DHA ve AA'nın sade, tarafsız, doğrulanabilir ve ters piramit haber dilini kullan. En önemli gelişmeyi ilk paragrafta ver; sonraki paragraflarda ayrıntı ve bağlamı aktar. Yalnızca verilen editoryal girdideki doğrulanabilir bilgileri kullan; kişi, tarih, sayı veya alıntı uydurma. Cümleleri ve paragraf yapısını baştan kurarak tamamen özgün yaz; metni kopyalama. Başlık yanıltıcı veya tık tuzağı olmasın. Google'ın yararlı, güvenilir ve insan odaklı içerik ilkelerine uygun yaz; anahtar kelime doldurma yapma. Soru-cevap, SSS, "Merak edilenler" bölümü, madde halinde soru-cevap veya sonuç özeti ekleme. Çıktının hiçbir yerinde kaynak adı, kaynak URL'si, bağlantı, dipnot, kaynakça veya hazırlanma açıklaması verme. "Bu haber...", "Bu içerik...", "Kaynak:", "haberine göre", "kaynağına göre", "aktardığına göre" ve benzeri kaynak açıklama kalıplarını kullanma. Resmî kurum kaynaklarında kurumun sitesine, duyurusuna, kurumsal mecrasına veya bilginin doğruluğuna atıf yapan açıklamalar yazma; olayı doğrudan haberleştir. Yalnızca şu yapıda saf JSON döndür:
{"title":"30-65 karakter haber başlığı","summary":"120-160 karakter meta açıklama","body":"yalnızca kaynak ayrıntılarının desteklediği, yaklaşık hedef uzunlukta ve en az 6 anlamlı paragraftan oluşan haber metni","focus_keyword":"doğal odak sorgu","keywords":["6-10 alakalı anahtar kelime veya sorgu"],"hashtags":["#EnFazla5Etiket"],"category":"tek ve genel haber kategorisi"}
PROMPT);
    }

    /** @param array<string, mixed> $promptSnapshot */
    private function userPrompt(RawNewsItem $rawNewsItem, array $promptSnapshot): string
    {
        $configuredTemplate = trim((string) ($promptSnapshot['user_prompt_template'] ?? ''));
        $body = Str::of(html_entity_decode(strip_tags($rawNewsItem->original_body), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->limit((int) $this->settings->get('ai.max_input_characters', $rawNewsItem->agency_id), '')
            ->toString();
        $sourceWordCount = count(preg_split('/\s+/u', $body) ?: []);
        $supportedLength = max(180, min(800, $sourceWordCount * 2));
        $targetLength = max(180, min($supportedLength, (int) ($promptSnapshot['target_length'] ?? 600)));
        $isTrendSignal = str_starts_with((string) $rawNewsItem->external_id, 'google-trends-')
            || str_starts_with((string) $rawNewsItem->external_id, 'x-trend-');
        $isContentIntent = $isTrendSignal && preg_match('/\b(mesajlari|sozleri|dualari|tarifi|tarifleri|yorumlari|etkinlikleri)\b/u', Str::lower(Str::ascii($rawNewsItem->original_title))) === 1;
        $trendInstruction = match (true) {
            $isContentIntent => 'Bu trend bir içerik arama niyetidir. Trend olduğunu anlatma; kullanıcıya doğrudan aradığı mesajları, sözleri, duaları, tarifleri, yorumları veya etkinlik önerilerini üret. Başlıkta ve gövdede "gündem oldu", "sosyal medyada", "trend", "arama hacmi" gibi ifadeler kullanma.',
            $isTrendSignal => 'Bu kayıt doğrulanmış haber kaynaklarıyla eşleştirilmiş bir trend keşif sinyalidir. Gündem kelimesini değil, neden gündem olduğunu oluşturan gerçek olayı haberleştir. Örneğin skor trendinde takım, rakip, sonuç ve sonuç doğuran gelişmeyi başlığa taşı. Başlıkta, özette ve haber gövdesinde "Google Trends", "X gündemi", "sosyal medyada gündem oldu", "ifadesi gündem oldu", "arama hacmi", "yükselişte" veya "haberle ilişkilendirildi" gibi sistem ifadeleri kullanma. Yetersiz ayrıntıyı uydurma.',
            default => '',
        };

        return <<<PROMPT
Editoryal talimat: {$configuredTemplate}
Trend talimatı: {$trendInstruction}
Hedef uzunluk: yaklaşık {$targetLength} kelime
Editoryal girdi kurumu: {$rawNewsItem->source_name}
Girdi başlığı: {$rawNewsItem->original_title}
Doğrulanmış girdi metni:
{$body}
PROMPT;
    }

    /** @return array{title: string, summary: string, body: string, focus_keyword: string, keywords: array<int, string>, hashtags: array<int, string>, category: string} */
    private function normalize(string $text): array
    {
        $decoded = $this->decodeJson($text);
        $title = Str::of(strip_tags($this->stripExternalLinks((string) ($decoded['title'] ?? ''))))->squish()->limit(220, '')->toString();
        $summary = Str::of(strip_tags($this->sanitizeAgencyBody((string) ($decoded['summary'] ?? ''))))->squish()->limit(320, '')->toString();
        $body = $this->sanitizeAgencyBody((string) ($decoded['body'] ?? ''));
        $focusKeyword = Str::of(strip_tags($this->stripExternalLinks((string) ($decoded['focus_keyword'] ?? ''))))->squish()->limit(120, '')->toString();
        $keywords = $this->stringList($decoded['keywords'] ?? [], 10);
        $hashtags = collect($this->stringList($decoded['hashtags'] ?? [], 5))
            ->map(fn (string $hashtag): string => '#'.Str::studly(Str::of($hashtag)->replaceStart('#', '')->toString()))
            ->filter(fn (string $hashtag): bool => $hashtag !== '#')
            ->values()
            ->all();
        $category = Str::of(strip_tags($this->stripExternalLinks((string) ($decoded['category'] ?? 'Gündem'))))->squish()->limit(80, '')->toString();

        if ($title === '' || Str::length(strip_tags($body)) < 100) {
            throw new RuntimeException('Sağlayıcı eksik veya geçersiz haber JSON verisi döndürdü.');
        }

        if ($summary === '') {
            $summary = Str::limit(strip_tags($body), 155, '');
        }

        $this->assertAgencyStyle($title, $summary, $body);

        return [
            'title' => $title,
            'summary' => $summary,
            'body' => $body,
            'focus_keyword' => $focusKeyword ?: ($keywords[0] ?? Str::lower($title)),
            'keywords' => $keywords,
            'hashtags' => $hashtags,
            'category' => $category ?: 'Gündem',
        ];
    }

    private function sanitizeAgencyBody(string $body): string
    {
        $body = trim($body);
        $body = preg_replace('/(?:#{1,6}\s*)?(?:Merak edilenler|Sıkça sorulan sorular|\bSSS\b).*?\z/isu', '', $body) ?? $body;
        $body = preg_replace('/(?:Not:\s*)?(?:Bu haber|Bu içerik)\b.*?\z/isu', '', $body) ?? $body;
        $body = preg_replace('/\bKaynak(?:ça)?:\s*.*?\z/isu', '', $body) ?? $body;
        $body = preg_replace('/\s*\((?:Kaynak|Kaynakça):\s*[^)]*\)/isu', '', $body) ?? $body;
        $body = preg_replace('/[^.!?\r\n]{0,180}\b(?:haberine|kaynağına|aktardığına)\s+göre\s*[,;:]?\s*/iu', ' ', $body) ?? $body;
        $body = $this->stripExternalLinks($body);
        $body = preg_replace('/[ \t]+([,.;:!?])/u', '$1', $body) ?? $body;
        $body = preg_replace('/[ \t]{2,}/u', ' ', $body) ?? $body;

        return trim($body);
    }

    private function stripExternalLinks(string $text): string
    {
        $text = preg_replace('/\[(?:[^\]]*)\]\((?:https?:\/\/|www\.)[^)]+\)/iu', '', $text) ?? $text;
        $text = preg_replace('~(?:https?://|www\.)[^\s<>\]\)]+~iu', '', $text) ?? $text;
        $text = preg_replace('/\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:com|net|org|tr|com\.tr|gov\.tr|bel\.tr)\b(?:\/[^\s<>\]\)]*)?/iu', '', $text) ?? $text;

        return $text;
    }

    private function assertAgencyStyle(string $title, string $summary, string $body): void
    {
        $output = $title."\n".$summary."\n".$body;
        $prohibitedPattern = '/(?:Merak edilenler|Sıkça sorulan sorular|\bSSS\b|Kaynak(?:ça)?:|https?:\/\/|(?:haberine|kaynağına|aktardığına)\s+göre|Google Trends|arama hacmi|haberle ilişkilendiril|Belediyenin yayımladığı duyuruda|kurumsal mecralarında|referans teşkil|resmî internet sitesindeki|resmi internet sitesindeki|bilgisi kamuoyuyla paylaşıldı)/iu';

        if (preg_match($prohibitedPattern, $output) === 1) {
            throw new RuntimeException('Sağlayıcı kurumsal haber dili yerine kaynak, trend veya soru-cevap metni döndürdü.');
        }
    }

    /** @return array<int, string> */
    private function stringList(mixed $value, int $limit): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,;\n]+/u', $value) ?: [];
        }

        return collect(is_array($value) ? $value : [])
            ->filter(fn (mixed $item): bool => is_scalar($item))
            ->map(fn (mixed $item): string => Str::of(strip_tags($this->stripExternalLinks((string) $item)))->squish()->limit(120, '')->toString())
            ->filter()
            ->unique(fn (string $item): string => Str::lower($item))
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $text): array
    {
        $text = trim(Str::of($text)->replaceMatches('/^```(?:json)?\s*|\s*```$/iu', '')->toString());
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        $json = $start === false || $end === false ? '' : substr($text, $start, $end - $start + 1);
        $decoded = $json === '' ? null : json_decode($json, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

        if (! is_array($decoded)) {
            throw new RuntimeException('Sağlayıcı yanıtı JSON olarak okunamadı: '.json_last_error_msg());
        }

        return $decoded;
    }

    private function maxOutputTokens(int $agencyId): int
    {
        return (int) $this->settings->get('ai.max_output_tokens', $agencyId);
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
