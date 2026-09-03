<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class NewsVisualFallback
{
    public function __construct(
        private readonly RenderedPageCapture $capture,
        private readonly AiVisionNewsExtractor $vision,
    ) {}

    /**
     * @return array<int, array{external_id: ?string, title: string, body: string, url: ?string, image_url: ?string, published_at: Carbon}>
     */
    public function extract(int $agencyId, string $sourceUrl, string $html): array
    {
        if (! config('news_ingestion.visual_fallback_enabled', true)) {
            throw new RuntimeException('Görsel yapay zekâ yedeği devre dışı.');
        }

        $imagePath = $this->capture->capture($html);

        try {
            $records = $this->vision->extract($agencyId, $imagePath);
        } finally {
            $this->capture->remove($imagePath);
        }

        $items = [];

        foreach ($records as $record) {
            $title = Str::squish(strip_tags((string) ($record['title'] ?? '')));
            $body = Str::squish(strip_tags((string) ($record['body'] ?? $record['summary'] ?? '')));

            if ($title === '' || mb_strlen($body) < 20) {
                continue;
            }

            $date = $record['published_at'] ?? null;
            $publishedAt = filled($date) ? rescue(fn (): Carbon => Carbon::parse((string) $date), now(), false) : now();
            $externalId = 'visual-'.hash('sha256', Str::lower($title));

            $items[] = [
                'external_id' => $externalId,
                'title' => Str::limit($title, 500, ''),
                'body' => $body,
                'url' => $sourceUrl,
                'image_url' => null,
                'published_at' => $publishedAt->isFuture() ? now() : $publishedAt,
            ];
        }

        if ($items === []) {
            throw new RuntimeException('Ekran görüntüsünde doğrulanabilir haber başlığı ve metni bulunamadı.');
        }

        return array_slice($items, 0, 20);
    }
}
