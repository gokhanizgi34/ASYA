<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Models\RawNewsItem;
use App\Models\TrendTopic;
use App\RawNewsStatus;
use App\TrendStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class ExternalTrendCollector
{
    public function __construct(
        private readonly ExternalUrlGuard $urlGuard,
        private readonly AutomaticNewsPipelineStarter $pipeline,
        private readonly NewsContentExtractor $newsContentExtractor,
        private readonly NewsContentQualityGate $qualityGate,
        private readonly NewsDuplicateDetector $duplicateDetector,
        private readonly SystemSettings $settings,
    ) {}

    /** @return array{received: int, imported: int, queued: int} */
    public function collect(int $agencyId): array
    {
        $googleDailyLimit = max(0, (int) $this->settings->get('trends.google_daily_item_limit', $agencyId));
        $googleImportedToday = RawNewsItem::query()
            ->where('agency_id', $agencyId)
            ->where('external_id', 'like', 'google-trends-%')
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();
        $googleImportedThisRun = 0;
        $googleItems = $googleImportedToday < $googleDailyLimit
            ? $this->googleTrends($agencyId)
            : [];
        $items = collect($googleItems)
            ->merge($this->xTrends($agencyId))
            ->unique(fn (array $item): string => Str::lower($item['source'].'|'.$item['title']))
            ->take((int) config('services.external_trends.max_items_per_run', 20));

        $rawNewsItemIds = [];
        $imported = 0;

        foreach ($items as $item) {
            $isGoogleTrend = Str::startsWith($item['external_id'], 'google-trends-');

            if ($isGoogleTrend && $googleImportedToday + $googleImportedThisRun >= $googleDailyLimit) {
                continue;
            }

            if ($item['score'] < (float) config('services.external_trends.min_traffic', 5000) || ! $this->isTurkeyRelevant($item)) {
                continue;
            }

            $checksum = hash('sha256', Str::lower($item['source'].'|'.$item['title']));

            if (RawNewsItem::withTrashed()->where('agency_id', $agencyId)->where('checksum', $checksum)->exists()
                || $this->duplicateDetector->exists($agencyId, $item['title'])) {
                continue;
            }

            $attributes = [
                'agency_id' => $agencyId,
                'external_id' => $item['external_id'],
                'source_name' => $item['source'],
                'source_url' => $item['url'],
                'original_title' => $item['title'],
                'original_body' => $item['body'],
                'original_image_url' => $item['image_url'],
                'language' => 'tr',
                'status' => RawNewsStatus::Pending,
                'priority' => min(100, 60 + (int) floor($item['score'] / 10)),
                'checksum' => $checksum,
                'discovered_at' => now(),
            ];

            $this->recordTrend($agencyId, $item);

            try {
                $this->qualityGate->assertRawNews(new RawNewsItem($attributes));
            } catch (\DomainException) {
                continue;
            }

            $rawNews = RawNewsItem::query()->create($attributes);
            $rawNewsItemIds[] = $rawNews->id;
            $imported++;

            if ($isGoogleTrend) {
                $googleImportedThisRun++;
            }
        }

        $batch = $this->pipeline->startForAgency(
            agencyId: $agencyId,
            rawNewsItemIds: $rawNewsItemIds,
            originLabel: 'Google Trends ve X Gündemi',
        );

        return [
            'received' => $items->count(),
            'imported' => $imported,
            'queued' => $batch?->total_items ?? 0,
        ];
    }

    /** @return array<int, array{external_id: string, source: string, title: string, body: string, url: string, image_url: ?string, score: float}> */
    private function googleTrends(int $agencyId): array
    {
        return Cache::remember('external-trends:google:'.config('services.external_trends.google_geo', 'TR'), now()->addMinutes(10), function () use ($agencyId): array {
            $url = (string) config('services.external_trends.google_rss_url', 'https://trends.google.com/trending/rss?geo=TR');
            $this->urlGuard->assertSafe($url);
            $response = Http::accept('application/rss+xml, application/xml;q=0.9')
                ->withUserAgent('ASYA-News-Automation/1.0')
                ->connectTimeout(8)
                ->timeout(20)
                ->get($url)
                ->throw();

            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (! $xml instanceof SimpleXMLElement) {
                throw new RuntimeException('Google Trends RSS yanıtı okunamadı.');
            }

            $namespace = $xml->getNamespaces(true)['ht'] ?? 'https://trends.google.com/trending/rss';
            $items = [];

            foreach ($xml->channel->item ?? [] as $item) {
                $trendName = Str::of((string) $item->title)->squish()->toString();
                $trendData = $item->children($namespace);
                $trafficLabel = Str::of((string) ($trendData->approx_traffic ?? ''))->squish()->toString();
                $newsItem = $trendData->news_item[0] ?? null;
                $newsData = $newsItem instanceof SimpleXMLElement ? $newsItem->children($namespace) : null;
                $relatedTitle = Str::of((string) ($newsData?->news_item_title ?? ''))->squish()->toString();
                $relatedSource = Str::of((string) ($newsData?->news_item_source ?? ''))->squish()->toString();
                $relatedUrl = trim((string) ($newsData?->news_item_url ?? ''));
                $imageUrl = trim((string) ($newsData?->news_item_picture ?? ''));

                if ($trendName === '') {
                    continue;
                }

                $url = filter_var($relatedUrl, FILTER_VALIDATE_URL)
                    ? $relatedUrl
                    : 'https://trends.google.com/trending?geo='.(string) config('services.external_trends.google_geo', 'TR');
                $relatedContent = filter_var($relatedUrl, FILTER_VALIDATE_URL)
                    ? $this->relatedNewsContent($relatedUrl, $relatedTitle, $agencyId)
                    : null;
                $title = $relatedContent['title'] ?? ($relatedTitle ?: $trendName);
                $body = $relatedContent['body'] ?? ('Trend keşif sinyali “'.$trendName.'” başlığıyla ilgilidir.'
                    .($trafficLabel !== '' ? ' Akışta bildirilen yaklaşık arama hacmi '.$trafficLabel.' seviyesindedir.' : '')
                    .($relatedTitle !== '' ? ' Trendle ilişkilendirilen güncel haber başlığı: “'.$relatedTitle.'”.' : '')
                    .($relatedSource !== '' ? ' İlişkili yayın kaynağı: '.$relatedSource.'.' : '')
                    .' Bu kayıt yalnızca keşif sinyalidir; haberleştirmede bağlantılı kaynakta doğrulanabilen bilgiler kullanılmalıdır.');

                $items[] = [
                    'external_id' => 'google-trends-'.hash('sha256', Str::lower($trendName)),
                    'source' => $relatedSource ?: 'Google Trends',
                    'title' => $title,
                    'body' => $body,
                    'url' => $url,
                    'image_url' => $relatedContent['image_url'] ?? (filter_var($imageUrl, FILTER_VALIDATE_URL) ? $imageUrl : null),
                    'score' => (float) $this->metricCount($trafficLabel),
                ];
            }

            return $items;
        });
    }

    /** @return array{title: string, body: string, image_url: ?string}|null */
    private function relatedNewsContent(string $url, string $expectedTitle, int $agencyId): ?array
    {
        try {
            $items = $this->newsContentExtractor->extract($url, $agencyId)['items'];
            $item = collect($items)->first(fn (array $candidate): bool => $candidate['url'] === $url)
                ?? collect($items)->first(fn (array $candidate): bool => $expectedTitle !== '' && Str::contains(Str::lower($candidate['title']), Str::lower(Str::limit($expectedTitle, 40, ''))))
                ?? $items[0] ?? null;

            if (! is_array($item) || blank($item['title'] ?? null) || mb_strlen((string) ($item['body'] ?? '')) < 80) {
                return null;
            }

            return [
                'title' => Str::of((string) $item['title'])->squish()->limit(500, '')->toString(),
                'body' => Str::of((string) $item['body'])->squish()->limit(30000, '')->toString(),
                'image_url' => filter_var($item['image_url'] ?? null, FILTER_VALIDATE_URL) ? $item['image_url'] : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<int, array{external_id: string, source: string, title: string, body: string, url: string, image_url: null, score: float}> */
    private function xTrends(int $agencyId): array
    {
        $integration = ApiIntegration::query()
            ->where('agency_id', $agencyId)
            ->whereIn('provider', [IntegrationProvider::XTrends, IntegrationProvider::SocialMedia])
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('base_url', 'like', '%api.x.com%')
                    ->orWhere('name', 'like', 'X %')
                    ->orWhere('name', 'like', '%Twitter%');
            })
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->first();

        if (! $integration || blank($integration->credential)) {
            return [];
        }

        $endpoint = rtrim((string) config('services.external_trends.x_endpoint', 'https://api.x.com/2/trends/by/woeid'), '/')
            .'/'.(int) config('services.external_trends.x_woeid', 23424969);
        $this->urlGuard->assertSafe($endpoint);
        $response = Http::acceptJson()
            ->withToken((string) $integration->credential)
            ->connectTimeout(8)
            ->timeout(20)
            ->get($endpoint, [
                'max_trends' => (int) config('services.external_trends.x_max_trends', 10),
                'trend.fields' => 'trend_name,tweet_count',
            ])
            ->throw();

        return collect((array) data_get($response->json(), 'data', []))
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['trend_name'] ?? null))
            ->map(function (array $item): array {
                $name = Str::of((string) $item['trend_name'])->squish()->toString();
                $tweetCount = max(0, (int) ($item['tweet_count'] ?? 0));

                return [
                    'external_id' => 'x-trend-'.hash('sha256', Str::lower($name)),
                    'source' => 'X Gündemi',
                    'title' => $name.' X gündeminde öne çıktı',
                    'body' => 'X Trends API verilerine göre “'.$name.'” başlığı Türkiye gündeminde öne çıktı.'
                        .($tweetCount > 0 ? ' API tarafından bildirilen paylaşım sayısı yaklaşık '.number_format($tweetCount, 0, ',', '.').' seviyesindedir.' : '')
                        .' Bu kayıt bir sosyal ağ gündem sinyalidir; kullanıcı iddiaları doğrulanmış gerçek gibi sunulmamalı, haber yalnızca trendin kapsamını ve kamuoyu ilgisini açıklamalıdır.',
                    'url' => 'https://x.com/search?q='.rawurlencode($name),
                    'image_url' => null,
                    'score' => (float) $tweetCount,
                ];
            })
            ->values()
            ->all();
    }

    /** @param array{source: string, title: string, url: string, score: float} $item */
    private function recordTrend(int $agencyId, array $item): void
    {
        $normalized = Str::of($item['title'])->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->limit(160, '')->toString();
        $score = min(100, max(1, $item['score'] > 0 ? log10($item['score'] + 1) * 20 : 20));

        TrendTopic::query()->updateOrCreate(
            ['agency_id' => $agencyId, 'normalized_name' => $normalized],
            [
                'name' => Str::limit($item['title'], 160, ''),
                'status' => $score >= 70 ? TrendStatus::Hot : TrendStatus::Rising,
                'mention_count' => max(1, (int) $item['score']),
                'source_count' => 1,
                'score' => round($score, 2),
                'velocity' => 100,
                'context' => ['provider' => $item['source'], 'url' => $item['url'], 'external' => true],
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'analyzed_at' => now(),
            ],
        );
    }

    /** @param array{title: string, body: string, url: string} $item */
    private function isTurkeyRelevant(array $item): bool
    {
        $host = Str::lower((string) parse_url($item['url'], PHP_URL_HOST));
        $text = Str::lower(Str::ascii($item['title'].' '.$item['body']));

        return str_ends_with($host, '.tr')
            || preg_match('/\b(turkiye|turk|istanbul|ankara|izmir|bursa|antalya|adana|kocaeli|pendik|umraniye|sancaktepe|cekmekoy|sultanbeyli)\b/', $text) === 1;
    }

    private function metricCount(string $value): int
    {
        $normalized = Str::upper(str_replace([',', ' ', '+'], ['', '', ''], $value));
        $multiplier = str_ends_with($normalized, 'M') ? 1_000_000 : (str_ends_with($normalized, 'K') ? 1_000 : 1);
        $number = (float) preg_replace('/[^0-9.]/', '', $normalized);

        return (int) round($number * $multiplier);
    }
}
