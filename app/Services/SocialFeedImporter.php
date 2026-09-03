<?php

namespace App\Services;

use App\Models\SocialFeedImport;
use App\Models\SocialFeedSource;
use App\Models\SocialMention;
use App\SocialFeedImportStatus;
use App\SocialMentionStatus;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SocialFeedImporter
{
    public function __construct(private SocialMentionAnalyzer $analyzer) {}

    /** @param array<int, mixed> $items */
    public function import(SocialFeedSource $source, array $items, ?int $userId): SocialFeedImport
    {
        $run = SocialFeedImport::create([
            'agency_id' => $source->agency_id,
            'social_feed_source_id' => $source->id,
            'started_by' => $userId,
            'status' => SocialFeedImportStatus::Running,
            'received_count' => count($items),
            'started_at' => now(),
        ]);

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($items as $index => $item) {
            try {
                if (! is_array($item)) {
                    throw new \InvalidArgumentException('Kayıt nesne biçiminde değil.');
                }

                $mapped = $this->map($source, $item);
                $validator = Validator::make($mapped, [
                    'external_id' => ['required', 'string', 'max:190'],
                    'content' => ['required', 'string', 'min:20', 'max:20000'],
                    'author_handle' => ['nullable', 'string', 'max:120'],
                    'url' => ['nullable', 'url:http,https', 'max:2048'],
                    'published_at' => ['required', 'date', 'before_or_equal:now'],
                    'engagement_count' => ['required', 'integer', 'min:0', 'max:1000000000'],
                ]);

                if ($validator->fails()) {
                    throw new \InvalidArgumentException($validator->errors()->first());
                }

                if (SocialMention::query()->where('agency_id', $source->agency_id)->where('platform', $source->platform)->where('external_id', $mapped['external_id'])->exists()) {
                    $skipped++;

                    continue;
                }

                $analysis = $this->analyzer->analyze($source->watch, $mapped['content'], (int) $mapped['engagement_count']);

                if ($analysis['matchedKeywords'] === []) {
                    $skipped++;

                    continue;
                }

                SocialMention::create([
                    'agency_id' => $source->agency_id,
                    'social_listening_watch_id' => $source->social_listening_watch_id,
                    'created_by' => $userId,
                    'platform' => $source->platform,
                    ...$mapped,
                    'sentiment' => $analysis['sentiment'],
                    'sentiment_score' => $analysis['sentimentScore'],
                    'urgency_score' => $analysis['urgencyScore'],
                    'matched_keywords' => $analysis['matchedKeywords'],
                    'status' => SocialMentionStatus::New,
                ]);
                $imported++;
            } catch (Throwable $exception) {
                $failed++;
                $errors[] = ['row' => $index + 1, 'message' => $exception->getMessage()];
            }
        }

        $status = match (true) {
            $failed > 0 && $imported === 0 && $skipped === 0 => SocialFeedImportStatus::Failed,
            $failed > 0 => SocialFeedImportStatus::Partial,
            default => SocialFeedImportStatus::Completed,
        };

        $run->update([
            'status' => $status,
            'imported_count' => $imported,
            'skipped_count' => $skipped,
            'failed_count' => $failed,
            'errors' => array_slice($errors, 0, 20),
            'completed_at' => now(),
        ]);
        $source->update(['last_imported_at' => now()]);

        return $run->fresh();
    }

    /** @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function map(SocialFeedSource $source, array $item): array
    {
        $map = $source->field_map;

        return [
            'external_id' => data_get($item, $map['external_id']),
            'content' => data_get($item, $map['content']),
            'author_handle' => data_get($item, $map['author_handle']),
            'url' => data_get($item, $map['url']),
            'published_at' => data_get($item, $map['published_at']),
            'engagement_count' => data_get($item, $map['engagement_count'], 0),
        ];
    }
}
