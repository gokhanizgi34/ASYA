<?php

namespace App\Services;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Models\RawNewsItem;
use App\Models\TrendTopic;
use App\RawNewsStatus;
use App\TrendStatus;
use DOMDocument;
use DOMElement;
use DOMXPath;
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
            ->unique(fn (array $item): string => Str::lower($item['source'].'|'.$item['title']));

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
        return Cache::remember('external-trends:google:'.config('services.external_trends.google_geo', 'TR'), now()->addMinutes(10), function (): array {
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

                $url = 'https://trends.google.com/explore?geo='.(string) config('services.external_trends.google_geo', 'TR');
                $title = $trendName;
                $body = 'Türkiye Google Trends keşif akışında “'.$trendName.'” araması öne çıktı. Bu başlık, kullanıcıların gün içinde bilgi aradığı ve gelişmeleri takip etmek istediği bir gündem sinyalidir.'
                    .($trafficLabel !== '' ? ' Akışta bildirilen yaklaşık ilgi seviyesi '.$trafficLabel.' olarak ölçüldü.' : '')
                    .' Arama ilgisinin nedeni, gelişmenin tarihi, kapsamı, ilgili kurumların açıklamaları ve vatandaşları ilgilendiren sonuçlar ayrı ayrı doğrulanarak haberleştirilmelidir. Gündemdeki gelişmenin ayrıntıları kamuoyuna bildirildi. Bu kayıt yalnızca arama ilgisi sinyalidir; doğrulanmamış iddialar haber metnine eklenmemelidir.';

                $items[] = [
                    'external_id' => 'google-trends-'.hash('sha256', Str::lower($trendName)),
                    'source' => 'Google Trends',
                    'title' => $title,
                    'body' => $body,
                    'url' => $url,
                    'image_url' => null,
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

    /** @return array<int, array{external_id: string, source: string, title: string, body: string, url: string, image_url: null, score: float, mention_count?: int}> */
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

        $apiItems = [];

        if ($integration && filled($integration->credential)) {
            try {
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

                $apiItems = collect((array) data_get($response->json(), 'data', []))
                    ->filter(fn (mixed $item): bool => is_array($item) && filled($item['trend_name'] ?? null))
                    ->map(function (array $item): array {
                        $name = Str::of((string) $item['trend_name'])->squish()->toString();
                        $tweetCount = max(0, (int) ($item['tweet_count'] ?? 0));

                        return $this->xTrendItem($name, (float) $tweetCount, $tweetCount);
                    })
                    ->values()
                    ->all();
            } catch (Throwable) {
            }
        }

        try {
            $feedItems = $this->xRssTrends();
        } catch (Throwable) {
            try {
                $feedItems = $this->xWebTrends();
            } catch (Throwable) {
                $feedItems = [];
            }
        }

        return collect([...$apiItems, ...$feedItems])
            ->sortByDesc('score')
            ->unique('external_id')
            ->take(max(1, (int) config('services.external_trends.x_max_trends', 10)))
            ->values()
            ->all();
    }

    /** @return array<int, array{external_id: string, source: string, title: string, body: string, url: string, image_url: null, score: float, mention_count: int}> */
    private function xRssTrends(): array
    {
        return Cache::remember('external-trends:x-rss:turkey', now()->addMinutes(10), function (): array {
            $url = (string) config('services.external_trends.x_rss_url');
            $this->urlGuard->assertSafe($url);
            $response = Http::accept('application/rss+xml, application/xml;q=0.9, text/xml;q=0.8')
                ->withUserAgent('ASYA-News-Automation/1.0')
                ->connectTimeout(8)
                ->timeout(20)
                ->get($url)
                ->throw();
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (! $xml instanceof SimpleXMLElement || ! isset($xml->channel->item[0])) {
                throw new RuntimeException('Türkiye X gündem RSS akışı okunamadı.');
            }

            $latest = $xml->channel->item[0];
            $namespaces = $latest->getNamespaces(true);
            $encoded = isset($namespaces['content']) ? (string) $latest->children($namespaces['content'])->encoded : '';
            $text = Str::of(html_entity_decode(strip_tags($encoded), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                ->replaceMatches('/^.*?Twitter Trends Turkey:\s*/iu', '')
                ->replaceMatches('/\s*\.\.\[top50\].*$/iu', '')
                ->squish()
                ->toString();
            preg_match_all('/(?:^|\s)\d+\)\s*(.+?)(?=\s+\d+\)|$)/u', $text, $matches);
            $limit = max(1, (int) config('services.external_trends.x_max_trends', 10));
            $minimumScore = max(1, (int) config('services.external_trends.min_traffic', 5000));
            $items = collect($matches[1] ?? [])
                ->map(fn (string $name): string => Str::of($name)->squish()->limit(160, '')->toString())
                ->filter()
                ->unique(fn (string $name): string => Str::lower($name))
                ->take($limit)
                ->values()
                ->map(fn (string $name, int $index): array => $this->xTrendItem($name, (float) ($minimumScore + $limit - $index), 0))
                ->all();

            if ($items === []) {
                throw new RuntimeException('Türkiye X gündem RSS akışında güncel konu bulunamadı.');
            }

            return $items;
        });
    }

    /** @return array<int, array{external_id: string, source: string, title: string, body: string, url: string, image_url: null, score: float, mention_count: int}> */
    private function xWebTrends(): array
    {
        return Cache::remember('external-trends:x-web:turkey', now()->addMinutes(10), function (): array {
            $url = (string) config('services.external_trends.x_web_url', 'https://trends24.in/turkey/');
            $this->urlGuard->assertSafe($url);
            $response = Http::accept('text/html')->withUserAgent('ASYA-News-Automation/1.0')->connectTimeout(8)->timeout(20)->get($url)->throw();
            $previous = libxml_use_internal_errors(true);
            $document = new DOMDocument;
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$response->body(), LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (! $loaded) {
                throw new RuntimeException('Türkiye X gündem sayfası okunamadı.');
            }

            $nodes = (new DOMXPath($document))->query("(//div[contains(concat(' ', normalize-space(@class), ' '), ' trend-card ')])[1]//a[contains(concat(' ', normalize-space(@class), ' '), ' trend-link ')]");
            $limit = max(1, (int) config('services.external_trends.x_max_trends', 10));
            $minimumScore = max(1, (int) config('services.external_trends.min_traffic', 5000));
            $items = [];

            foreach ($nodes ?: [] as $index => $node) {
                if (! $node instanceof DOMElement || count($items) >= $limit) {
                    continue;
                }

                $name = Str::of(html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'))->squish()->limit(160, '')->toString();
                if ($name === '') {
                    continue;
                }

                $items[] = $this->xTrendItem($name, (float) ($minimumScore + max(0, $limit - $index)), 0);
            }

            if ($items === []) {
                throw new RuntimeException('Türkiye X gündem sayfasında güncel konu bulunamadı.');
            }

            return $items;
        });
    }

    /** @return array{external_id: string, source: string, title: string, body: string, url: string, image_url: null, score: float, mention_count: int} */
    private function xTrendItem(string $name, float $score, int $mentionCount): array
    {
        return [
            'external_id' => 'x-trend-'.hash('sha256', Str::lower($name)),
            'source' => 'X Gündemi',
            'title' => $name.' X gündeminde öne çıktı',
            'body' => '“'.$name.'” başlığı Türkiye X gündem listesinde öne çıktı. RSS akışında başlığın güncel Türkiye sıralamasında yer aldığı bildirildi. Akış, Türkiye saat dilimi ve Türkçe dil seçeneğiyle yayımlanan listenin en yeni kaydından alındı. RSS verisi konu başlığını ve sıralamasını sağlıyor ancak paylaşım metinlerini, kişi iddialarını veya olay ayrıntılarını doğrulamıyor. Sıralama yalnızca kamuoyu ilgisini gösterir ve başlık altındaki iddiaların doğruluğunu kanıtlamaz. Haber hazırlanırken konu güvenilir haber kaynakları ve resmî açıklamalarla karşılaştırılmalıdır.',
            'url' => 'https://x.com/search?q='.rawurlencode($name),
            'image_url' => null,
            'score' => $score,
            'mention_count' => $mentionCount,
        ];
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
                'mention_count' => max(0, (int) ($item['mention_count'] ?? $item['score'])),
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
        if ($item['source'] === 'X Gündemi') {
            return true;
        }

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
