<?php

namespace App\Services;

use App\Models\RawNewsItem;
use DomainException;
use Illuminate\Support\Str;

class ContentTransformer
{
    public function __construct(
        private readonly AiNewsWriter $aiNewsWriter,
        private readonly NewsContentQualityGate $qualityGate,
    ) {}

    /**
     * @param  array<string, mixed>  $promptSnapshot
     * @return array{title: string, summary: string, body: string, focus_keyword?: string, keywords?: array<int, string>, hashtags?: array<int, string>, category?: string, ai_provider?: string}
     */
    public function transform(RawNewsItem $rawNewsItem, array $promptSnapshot, bool $requireAi = false): array
    {
        $title = Str::of(strip_tags($rawNewsItem->original_title))->squish()->limit(220, '')->toString();
        $body = Str::of(html_entity_decode(strip_tags($rawNewsItem->original_body), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();

        if ($title === '') {
            throw new DomainException('Ham haber başlığı boş olamaz.');
        }

        $this->qualityGate->assertRawNews($rawNewsItem);

        if ($this->aiNewsWriter->hasActiveIntegration($rawNewsItem->agency_id)) {
            return $this->aiNewsWriter->write($rawNewsItem, $promptSnapshot);
        }

        if ($requireAi) {
            throw new DomainException('Otomatik haber üretimi için aktif bir yapay zekâ API entegrasyonu gereklidir.');
        }

        $targetLength = max(100, min(5000, (int) ($promptSnapshot['target_length'] ?? 600)));
        $limitedBody = Str::words($body, $targetLength, '');
        $summary = Str::limit($limitedBody, 320);

        return [
            'title' => $title,
            'summary' => $summary,
            'body' => $limitedBody,
        ];
    }
}
