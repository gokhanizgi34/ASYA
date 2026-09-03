<?php

namespace App\Services;

use App\Models\NewsSource;
use App\Models\RawNewsItem;
use App\RawNewsStatus;
use Illuminate\Support\Str;
use RuntimeException;

class NewsFeedImporter
{
    public function __construct(
        private readonly NewsContentExtractor $extractor,
        private readonly BlacklistMatcher $blacklistMatcher,
        private readonly NewsContentQualityGate $qualityGate,
        private readonly NewsDuplicateDetector $duplicateDetector,
    ) {}

    /** @return array{received: int, imported: int, skipped: int, method: string, item_ids: array<int, int>, daily_limit: int, daily_remaining: int} */
    public function import(NewsSource $source): array
    {
        if (! $source->is_active || blank($source->feed_url)) {
            throw new RuntimeException('Kaynak etkin değil veya haber sayfası adresi girilmemiş.');
        }

        $dailyLimit = max(1, (int) $source->daily_item_limit);
        $usedToday = RawNewsItem::query()
            ->where('news_source_id', $source->id)
            ->whereDate('created_at', today())
            ->count();
        $remaining = max(0, $dailyLimit - $usedToday);

        if ($remaining === 0) {
            return [
                'received' => 0,
                'imported' => 0,
                'skipped' => 0,
                'method' => 'daily_quota_reached',
                'item_ids' => [],
                'daily_limit' => $dailyLimit,
                'daily_remaining' => 0,
            ];
        }

        $extraction = $this->extractor->extract((string) $source->feed_url, $source->agency_id);
        $items = $extraction['items'];
        $imported = 0;
        $skipped = 0;
        $itemIds = [];

        foreach (array_slice($items, 0, min(50, $remaining)) as $item) {
            $checksum = hash('sha256', Str::lower($item['url'] ?: $source->domain).'|'.Str::lower($item['title']));

            $existingItem = RawNewsItem::withTrashed()
                ->where('agency_id', $source->agency_id)
                ->where('checksum', $checksum)
                ->first();

            if (! $existingItem && $this->duplicateDetector->exists($source->agency_id, $item['title'])) {
                $skipped++;

                continue;
            }

            $blacklist = $this->blacklistMatcher->evaluate($source->agency_id, [
                'title' => $item['title'],
                'body' => $item['body'],
                'source_name' => $source->name,
                'source_url' => $item['url'],
            ]);

            if ($existingItem) {
                $newBodyLength = mb_strlen($item['body']);
                $existingBodyLength = mb_strlen($existingItem->original_body);
                $existingContentIsInvalid = false;

                try {
                    $this->qualityGate->assertRawNews($existingItem);
                } catch (\DomainException) {
                    $existingContentIsInvalid = true;
                }

                if ($existingItem->trashed()
                    || (! $existingContentIsInvalid && $newBodyLength < max(350, (int) ($existingBodyLength * 1.25)))) {
                    $skipped++;

                    continue;
                }

                $existingItem->forceFill([
                    'news_source_id' => $source->id,
                    'external_id' => $item['external_id'],
                    'source_name' => $source->name,
                    'source_url' => $item['url'],
                    'original_title' => $item['title'],
                    'original_body' => $item['body'],
                    'original_image_url' => $item['image_url'],
                    'status' => $blacklist['blocked'] ? RawNewsStatus::Rejected : ($blacklist['requires_review'] ? RawNewsStatus::Review : RawNewsStatus::Pending),
                    'discovered_at' => $item['published_at'],
                    'expires_at' => now()->addDays(2),
                    'processed_at' => null,
                    'failure_message' => $blacklist['matches']->isEmpty() ? null : 'Kara liste eşleşmesi: '.$blacklist['matches']->pluck('pattern')->take(5)->implode(', '),
                ])->save();
                $this->applyQualityStatus($existingItem);
                if ($existingItem->status === RawNewsStatus::Pending) {
                    $itemIds[] = $existingItem->id;
                }
                $imported++;

                continue;
            }

            $createdItem = RawNewsItem::query()->create([
                'agency_id' => $source->agency_id,
                'news_source_id' => $source->id,
                'external_id' => $item['external_id'],
                'source_name' => $source->name,
                'source_url' => $item['url'],
                'original_title' => $item['title'],
                'original_body' => $item['body'],
                'original_image_url' => $item['image_url'],
                'language' => 'tr',
                'status' => $blacklist['blocked'] ? RawNewsStatus::Rejected : ($blacklist['requires_review'] ? RawNewsStatus::Review : RawNewsStatus::Pending),
                'priority' => 50,
                'checksum' => $checksum,
                'discovered_at' => $item['published_at'],
                'expires_at' => now()->addDays(2),
                'failure_message' => $blacklist['matches']->isEmpty() ? null : 'Kara liste eşleşmesi: '.$blacklist['matches']->pluck('pattern')->take(5)->implode(', '),
            ]);
            $this->applyQualityStatus($createdItem);

            if ($createdItem->status === RawNewsStatus::Pending) {
                $itemIds[] = $createdItem->id;
            }
            $imported++;
        }

        $previousFingerprint = $source->last_content_fingerprint;
        $source->forceFill([
            'feed_url' => $extraction['url'],
            'feed_format' => $extraction['method'] === 'rss_atom_xml' ? 'auto' : 'smart',
            'last_fetched_at' => now(),
            'last_status_code' => $extraction['status'],
            'last_item_count' => count($items),
            'last_fetch_error' => null,
            'last_ingestion_method' => $extraction['method'],
            'last_content_fingerprint' => $extraction['fingerprint'],
            'last_change_detected_at' => $previousFingerprint !== $extraction['fingerprint'] ? now() : $source->last_change_detected_at,
            'last_crawled_pages' => $extraction['crawled_pages'],
        ])->save();

        return [
            'received' => count($items),
            'imported' => $imported,
            'skipped' => $skipped,
            'method' => $extraction['method'],
            'item_ids' => $itemIds,
            'daily_limit' => $dailyLimit,
            'daily_remaining' => max(0, $remaining - $imported),
        ];
    }

    private function applyQualityStatus(RawNewsItem $rawNewsItem): void
    {
        if ($rawNewsItem->status !== RawNewsStatus::Pending) {
            return;
        }

        try {
            $this->qualityGate->assertRawNews($rawNewsItem);
        } catch (\DomainException $exception) {
            $rawNewsItem->forceFill([
                'status' => RawNewsStatus::Review,
                'failure_message' => $exception->getMessage(),
            ])->save();
        }
    }
}
