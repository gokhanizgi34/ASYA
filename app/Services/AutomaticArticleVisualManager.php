<?php

namespace App\Services;

use App\CopyrightStatus;
use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Models\Article;
use App\Models\VisualAsset;
use App\VisualAssetStatus;
use App\VisualSourceType;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AutomaticArticleVisualManager
{
    public function __construct(
        private readonly AiIntegrationRegistry $registry,
        private readonly ExternalUrlGuard $urlGuard,
        private readonly NativeTlsHttpFetcher $nativeTlsHttpFetcher,
        private readonly SystemSettings $settings,
    ) {}

    public function ensure(
        Article $article,
        ?string $sourceImageUrl = null,
        ?string $sourcePageUrl = null,
        bool $allowInsecureTls = false,
    ): ?VisualAsset {
        $selected = $article->selectedVisualAsset()->where('status', VisualAssetStatus::Approved)->first();

        if ($selected && filled($selected->storage_path) && Storage::disk($selected->storage_disk)->exists($selected->storage_path)) {
            return $selected;
        }

        if (filled($sourceImageUrl)) {
            try {
                return $this->importSourceImage($article, $sourceImageUrl, $sourcePageUrl, $allowInsecureTls);
            } catch (Throwable $exception) {
                Log::warning('Kaynak haber görseli indirilemedi.', [
                    'article_id' => $article->id,
                    'source_image_url' => $sourceImageUrl,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        try {
            $pixabayVisual = $this->pixabayAllowed($article, $sourcePageUrl)
                ? $this->importPixabayImage($article)
                : null;

            if ($pixabayVisual) {
                return $pixabayVisual;
            }
        } catch (Throwable $exception) {
            Log::warning('Pixabay uygun bir görsel sağlayamadı.', [
                'article_id' => $article->id,
                'message' => $exception->getMessage(),
            ]);
        }

        if ($this->settings->get('visual.ai_generation_enabled', $article->agency_id)) {
            try {
                return $this->generateImage($article);
            } catch (Throwable $exception) {
                Log::warning('AI görseli üretilemedi.', [
                    'article_id' => $article->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if (filled($sourcePageUrl) || $this->pixabayAllowed($article)) {
            return null;
        }

        return $this->importAgencyLogo($article);
    }

    public function importUploadedImage(Article $article, UploadedFile $image): VisualAsset
    {
        return $this->storeImage(
            article: $article,
            bytes: (string) $image->get(),
            sourceType: VisualSourceType::Upload,
            copyrightStatus: CopyrightStatus::Original,
            sourceUrl: null,
            generationPrompt: null,
        );
    }

    private function importAgencyLogo(Article $article): ?VisualAsset
    {
        $article->loadMissing('agency');
        $logoPath = $article->agency?->logo_path;

        if (blank($logoPath) || ! Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        return $this->storeImage(
            article: $article,
            bytes: (string) Storage::disk('public')->get($logoPath),
            sourceType: VisualSourceType::Upload,
            copyrightStatus: CopyrightStatus::Original,
            sourceUrl: null,
            generationPrompt: null,
        );
    }

    private function pixabayAllowed(Article $article, ?string $sourcePageUrl = null): bool
    {
        $contentType = (string) data_get($article->editorial_metadata, 'content_type', '');

        return filled($sourcePageUrl)
            || filled($article->source_url)
            || in_array($contentType, ['news', 'topic_ai', 'horoscope', 'recipe', 'special_day', 'campaign', 'column'], true);
    }

    private function importSourceImage(
        Article $article,
        string $sourceImageUrl,
        ?string $sourcePageUrl,
        bool $allowInsecureTls,
    ): VisualAsset {
        $this->urlGuard->assertSafe($sourceImageUrl);
        $request = Http::accept('image/*')
            ->withUserAgent('ASYA-News-Automation/1.0')
            ->connectTimeout(10)
            ->timeout(30);

        if (filled($sourcePageUrl)) {
            $this->urlGuard->assertSafe($sourcePageUrl);
            $request = $request->withHeaders(['Referer' => $sourcePageUrl]);
        }

        try {
            $response = $request->get($sourceImageUrl);
        } catch (ConnectionException $exception) {
            if (! $allowInsecureTls && ! str_contains($exception->getMessage(), 'cURL error 60')) {
                throw $exception;
            }

            $response = $this->nativeTlsHttpFetcher->fetch(
                $sourceImageUrl,
                'image/*',
                'ASYA-News-Automation/1.0',
                20 * 1024 * 1024,
                $allowInsecureTls,
            );
        }
        $response->throw();

        return $this->storeImage(
            article: $article,
            bytes: $response->body(),
            sourceType: VisualSourceType::Original,
            copyrightStatus: CopyrightStatus::Unknown,
            sourceUrl: $sourceImageUrl,
            generationPrompt: null,
        );
    }

    private function importPixabayImage(Article $article): ?VisualAsset
    {
        $integration = ApiIntegration::query()
            ->where('agency_id', $article->agency_id)
            ->where('provider', IntegrationProvider::Pixabay)
            ->where('is_active', true)
            ->where('visual_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->first();

        if (! $integration || blank($integration->credential)) {
            return null;
        }

        $search = $this->pixabaySearch($article);
        $this->urlGuard->assertSafe($integration->base_url);
        $response = Http::acceptJson()
            ->withUserAgent('ASYA-News-Automation/1.0')
            ->connectTimeout(10)
            ->timeout(max(15, $integration->timeout_seconds))
            ->get($integration->base_url, [
                'key' => (string) $integration->credential,
                'q' => $search['query'],
                'lang' => $search['language'],
                'image_type' => 'photo',
                'orientation' => 'horizontal',
                'safesearch' => 'true',
                'order' => 'popular',
                'per_page' => 50,
            ]);
        $response->throw();

        $hit = $this->selectPixabayHit($article, (array) $response->json('hits', []));

        if ($hit === null) {
            return null;
        }

        $imageUrl = (string) data_get($hit, 'largeImageURL', data_get($hit, 'webformatURL'));
        $this->urlGuard->assertSafe($imageUrl);
        $imageResponse = Http::accept('image/*')
            ->withUserAgent('ASYA-News-Automation/1.0')
            ->connectTimeout(10)
            ->timeout(30)
            ->get($imageUrl);
        $imageResponse->throw();

        return $this->storeImage(
            article: $article,
            bytes: $imageResponse->body(),
            sourceType: VisualSourceType::Archive,
            copyrightStatus: CopyrightStatus::Licensed,
            sourceUrl: (string) data_get($hit, 'pageURL', $imageUrl),
            generationPrompt: 'Pixabay araması: '.$search['query'].' · etiketler: '.Str::limit((string) data_get($hit, 'tags', ''), 300, ''),
        );
    }

    /** @return array{query: string, language: string} */
    private function pixabaySearch(Article $article): array
    {
        $contentType = (string) data_get($article->editorial_metadata, 'content_type', 'news');
        $category = (string) data_get($article->editorial_metadata, 'category', '');
        $title = Str::of($article->title)->stripTags()->replaceMatches('/[^\pL\pN\s-]+/u', ' ')->squish()->toString();
        $query = match ($contentType) {
            'horoscope' => 'zodiac astrology horoscope constellation stars',
            'recipe' => 'Türk mutfağı yemek tabak '.$title,
            'special_day' => 'kutlama bayram etkinlik '.$title,
            'campaign' => 'kampanya etkinlik tanıtım '.$title,
            'column' => 'gazete gündem '.$title,
            default => $title.' '.$category,
        };

        return [
            'query' => Str::limit(Str::squish($query), 100, ''),
            'language' => $contentType === 'horoscope' ? 'en' : 'tr',
        ];
    }

    /**
     * @param  array<int, mixed>  $hits
     * @return array<string, mixed>|null
     */
    private function selectPixabayHit(Article $article, array $hits): ?array
    {
        $contentType = (string) data_get($article->editorial_metadata, 'content_type', 'news');
        $articleTerms = $this->pixabayTerms($article->title.' '.(string) data_get($article->editorial_metadata, 'category', ''));
        $blockedTerms = ['animal', 'animals', 'wildlife', 'seal', 'sealion', 'fok', 'köpek', 'kedi', 'kuş', 'hayvan', 'zoo'];
        $requiredTerms = match ($contentType) {
            'horoscope' => ['zodiac', 'astrology', 'horoscope', 'constellation', 'stars', 'astroloji', 'burç'],
            'recipe' => ['food', 'meal', 'dish', 'cuisine', 'cooking', 'kitchen', 'dessert', 'soup', 'salad', 'yemek', 'mutfak', 'tarif', 'tabak'],
            'special_day' => ['celebration', 'holiday', 'festival', 'event', 'flag', 'kutlama', 'bayram', 'etkinlik'],
            'campaign' => ['campaign', 'event', 'shopping', 'sale', 'promotion', 'kampanya', 'etkinlik'],
            default => [],
        };

        return collect($hits)
            ->filter(fn (mixed $hit): bool => is_array($hit) && filled(data_get($hit, 'largeImageURL', data_get($hit, 'webformatURL'))))
            ->map(function (array $hit) use ($articleTerms, $blockedTerms, $requiredTerms, $contentType): ?array {
                $tagTerms = $this->pixabayTerms((string) data_get($hit, 'tags', ''));
                $overlap = count(array_intersect($articleTerms, $tagTerms));
                $hasRequiredContext = $requiredTerms !== [] && array_intersect($requiredTerms, $tagTerms) !== [];
                $hasBlockedContext = array_intersect($blockedTerms, $tagTerms) !== [];
                $width = (int) data_get($hit, 'imageWidth', 0);
                $height = (int) data_get($hit, 'imageHeight', 0);
                $isLandscape = $height > 0 && ($width / $height) >= 1.15;
                $isRelevant = match ($contentType) {
                    'horoscope' => $hasRequiredContext,
                    'recipe', 'special_day', 'campaign' => $hasRequiredContext || $overlap > 0,
                    default => $overlap > 0,
                };

                if ($hasBlockedContext || ! $isLandscape || ! $isRelevant) {
                    return null;
                }

                return [
                    ...$hit,
                    '_asya_score' => ($overlap * 1000)
                        + ($hasRequiredContext ? 500 : 0)
                        + min(300, (int) data_get($hit, 'likes', 0))
                        + min(200, (int) floor($width / 20)),
                ];
            })
            ->filter()
            ->sortByDesc('_asya_score')
            ->first();
    }

    /** @return array<int, string> */
    private function pixabayTerms(string $value): array
    {
        $stopWords = ['bir', 'ile', 'icin', 'gibi', 'olan', 'olarak', 'son', 'the', 'and', 'for', 'turkiye', 'turkey', 'haber', 'news', 'gunluk', 'yorumlari', 'tarifi'];
        $normalized = Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();

        return collect(explode(' ', $normalized))
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3 && ! in_array($term, $stopWords, true))
            ->unique()
            ->values()
            ->all();
    }

    private function generateImage(Article $article): VisualAsset
    {
        $integrations = $this->registry->forAgency($article->agency_id)
            ->filter(fn (ApiIntegration $candidate): bool => $candidate->visual_enabled)
            ->filter(fn (ApiIntegration $candidate): bool => in_array($candidate->provider, [IntegrationProvider::GoogleGemini, IntegrationProvider::OpenAi], true));

        if ($integrations->isEmpty()) {
            throw new RuntimeException('Görsel üretimini destekleyen aktif bir OpenAI veya Google Gemini entegrasyonu bulunamadı.');
        }

        $asset = VisualAsset::query()
            ->where('article_id', $article->id)
            ->where('source_type', VisualSourceType::AiGenerated)
            ->first();

        $prompt = $this->imagePrompt($article);
        $asset ??= new VisualAsset;
        $asset->forceFill([
            'agency_id' => $article->agency_id,
            'article_id' => $article->id,
            'uploaded_by' => $article->author_id,
            'title' => $article->title.' kapak görseli',
            'source_type' => VisualSourceType::AiGenerated,
            'status' => VisualAssetStatus::Generating,
            'copyright_status' => CopyrightStatus::Original,
            'source_url' => null,
            'storage_disk' => 'public',
            'storage_path' => null,
            'quality_score' => 0,
            'alt_text' => $article->title,
            'generation_prompt' => $prompt,
            'failure_message' => null,
            'is_selected' => false,
        ])->save();

        $lastException = null;

        foreach ($integrations as $integration) {
            try {
                $bytes = $this->requestGeneratedImage($integration, $prompt);

                return $this->storeImage(
                    article: $article,
                    bytes: $bytes,
                    sourceType: VisualSourceType::AiGenerated,
                    copyrightStatus: CopyrightStatus::Original,
                    sourceUrl: null,
                    generationPrompt: $prompt,
                    asset: $asset,
                );
            } catch (Throwable $exception) {
                $lastException = $exception;
                Log::warning('AI görsel sağlayıcısı başarısız oldu; sıradaki sağlayıcı deneniyor.', [
                    'article_id' => $article->id,
                    'provider' => $integration->provider->value,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $message = $lastException?->getMessage() ?? 'AI görsel sağlayıcıları geçerli bir görsel döndürmedi.';
        $asset->forceFill([
            'status' => VisualAssetStatus::Failed,
            'failure_message' => Str::limit($message, 1900),
            'is_selected' => false,
        ])->save();

        throw new RuntimeException('AI görsel üretimi tamamlanamadı. '.Str::limit($message, 1500), previous: $lastException);
    }

    private function requestGeneratedImage(ApiIntegration $integration, string $prompt): string
    {
        return match ($integration->provider) {
            IntegrationProvider::OpenAi => $this->requestOpenAiImage($integration, $prompt),
            IntegrationProvider::GoogleGemini => $this->requestGeminiImage($integration, $prompt),
            default => throw new RuntimeException('Bu yapay zekâ sağlayıcısı görsel üretimini desteklemiyor.'),
        };
    }

    private function requestOpenAiImage(ApiIntegration $integration, string $prompt): string
    {
        $url = $this->imageEndpoint($integration->base_url);
        $this->urlGuard->assertSafe($url);
        $response = Http::acceptJson()
            ->asJson()
            ->withToken((string) $integration->credential)
            ->connectTimeout(10)
            ->timeout(max(120, $integration->timeout_seconds))
            ->post($url, [
                'model' => (string) config('services.openai.image_model', 'gpt-image-2'),
                'prompt' => $prompt,
                'size' => (string) config('services.openai.image_size', '1536x1024'),
                'quality' => (string) config('services.openai.image_quality', 'medium'),
            ]);
        $response->throw();

        return $this->decodeImage((string) data_get($response->json(), 'data.0.b64_json', ''), 'OpenAI');
    }

    private function requestGeminiImage(ApiIntegration $integration, string $prompt): string
    {
        $root = preg_replace('~/models(?:\?.*)?$~', '', rtrim($integration->base_url, '/')) ?: '';
        $model = (string) config('services.gemini.image_model', 'gemini-3.1-flash-image');
        $url = $root.'/models/'.rawurlencode($model).':generateContent';
        $this->urlGuard->assertSafe($url);
        $response = Http::acceptJson()
            ->asJson()
            ->withHeader('x-goog-api-key', (string) $integration->credential)
            ->connectTimeout(10)
            ->timeout(max(120, $integration->timeout_seconds))
            ->post($url, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                ],
            ]);
        $response->throw();
        $parts = data_get($response->json(), 'candidates.0.content.parts', []);
        $part = is_array($parts) ? collect($parts)->first(fn (mixed $item): bool => is_array($item) && filled(data_get($item, 'inlineData.data'))) : null;

        return $this->decodeImage((string) data_get($part, 'inlineData.data', ''), 'Google Gemini');
    }

    private function decodeImage(string $encodedImage, string $provider): string
    {
        $bytes = base64_decode($encodedImage, true);

        if ($encodedImage === '' || $bytes === false) {
            throw new RuntimeException($provider.' görsel yanıtı geçerli bir base64 dosyası içermiyor.');
        }

        return $bytes;
    }

    private function imageEndpoint(string $baseUrl): string
    {
        $url = rtrim($baseUrl, '/');

        if (preg_match('~/models(?:\?.*)?$~', $url) === 1) {
            return (string) preg_replace('~/models(?:\?.*)?$~', '/images/generations', $url);
        }

        if (str_ends_with($url, '/v1')) {
            return $url.'/images/generations';
        }

        return str_ends_with($url, '/images/generations') ? $url : $url.'/images/generations';
    }

    private function imagePrompt(Article $article): string
    {
        return 'Türkçe haber sitesi için yatay, fotogerçekçi ve profesyonel kapak görseli üret. '
            .'Görselde yazı, logo, filigran veya yanıltıcı ayrıntı kullanma. Haber başlığı: '
            .$article->title.'. Haber özeti: '.Str::limit((string) $article->summary, 500, '');
    }

    private function storeImage(
        Article $article,
        string $bytes,
        VisualSourceType $sourceType,
        CopyrightStatus $copyrightStatus,
        ?string $sourceUrl,
        ?string $generationPrompt,
        ?VisualAsset $asset = null,
    ): VisualAsset {
        if ($bytes === '' || strlen($bytes) > 20 * 1024 * 1024) {
            throw new RuntimeException('Haber görseli boş veya 20 MB sınırını aşıyor.');
        }

        $info = @getimagesizefromstring($bytes);
        $mimeType = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Haber görseli desteklenen JPEG, PNG veya WebP biçiminde değil.'),
        };
        $storagePath = 'visual-assets/automatic/'.$article->agency_id.'/'.$article->id.'-'.Str::uuid().'.'.$extension;

        if (! Storage::disk('public')->put($storagePath, $bytes)) {
            throw new RuntimeException('Haber görseli depolama alanına kaydedilemedi.');
        }

        $article->visualAssets()->update(['is_selected' => false]);
        $asset ??= new VisualAsset;
        $asset->forceFill([
            'agency_id' => $article->agency_id,
            'article_id' => $article->id,
            'uploaded_by' => $article->author_id,
            'title' => $article->title.' kapak görseli',
            'source_type' => $sourceType,
            'status' => VisualAssetStatus::Approved,
            'copyright_status' => $copyrightStatus,
            'source_url' => $sourceUrl,
            'storage_disk' => 'public',
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'quality_score' => 100,
            'alt_text' => $article->title,
            'generation_prompt' => $generationPrompt,
            'evaluation_notes' => 'Otomatik üretim bandı tarafından yayın için hazırlandı.',
            'failure_message' => null,
            'is_selected' => true,
            'generated_at' => $sourceType === VisualSourceType::AiGenerated ? now() : null,
            'evaluated_at' => now(),
        ])->save();

        return $asset;
    }
}
