<?php

namespace App\Services;

use App\ContentBatchItemStatus;
use App\ContentBatchStatus;
use App\Jobs\ProcessContentBatch;
use App\Models\AiPrompt;
use App\Models\ContentBatch;
use App\Models\NewsSource;
use App\Models\PublishingTarget;
use App\Models\RawNewsItem;
use App\Models\User;
use App\PromptTone;
use App\RawNewsStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomaticNewsPipelineStarter
{
    public function __construct(private readonly AiIntegrationRegistry $aiIntegrations) {}

    /** @param array<int, int> $rawNewsItemIds */
    public function start(NewsSource $source, array $rawNewsItemIds): ?ContentBatch
    {
        return $this->startForAgency(
            agencyId: $source->agency_id,
            rawNewsItemIds: $rawNewsItemIds,
            originLabel: $source->name,
            preferredCreatorId: $source->created_by,
            newsSourceId: $source->id,
        );
    }

    /** @param array<int, int> $rawNewsItemIds */
    public function startForAgency(
        int $agencyId,
        array $rawNewsItemIds,
        string $originLabel,
        ?int $preferredCreatorId = null,
        ?int $newsSourceId = null,
    ): ?ContentBatch {
        $ids = collect($rawNewsItemIds)->map(fn (mixed $id): int => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return null;
        }

        $creator = $this->creator($agencyId, $preferredCreatorId);
        $prompt = $this->prompt($agencyId);
        $hasAi = $this->aiIntegrations->forAgency($agencyId)->isNotEmpty();
        $hasPublishingTarget = PublishingTarget::query()
            ->where('agency_id', $agencyId)
            ->where('is_active', true)
            ->exists();

        if (! $creator || ! $prompt || ! $hasAi || ! $hasPublishingTarget) {
            Log::warning('Otomatik haber üretim bandı hazır değil; ham haberler beklemede tutuluyor.', [
                'news_source_id' => $newsSourceId,
                'agency_id' => $agencyId,
                'missing_creator' => ! $creator,
                'missing_prompt' => ! $prompt,
                'missing_ai_integration' => ! $hasAi,
                'missing_publishing_target' => ! $hasPublishingTarget,
            ]);

            return null;
        }

        $batch = DB::transaction(function () use ($agencyId, $ids, $creator, $prompt, $originLabel, $newsSourceId): ?ContentBatch {
            $rawNewsItems = RawNewsItem::query()
                ->where('agency_id', $agencyId)
                ->whereIn('id', $ids)
                ->whereIn('status', [RawNewsStatus::Pending, RawNewsStatus::Failed])
                ->lockForUpdate()
                ->get();

            if ($rawNewsItems->isEmpty()) {
                return null;
            }

            $batch = ContentBatch::query()->create([
                'agency_id' => $agencyId,
                'created_by' => $creator->id,
                'ai_prompt_id' => $prompt->id,
                'name' => 'Otomatik haber üretimi · '.$originLabel.' · '.now()->format('d.m.Y H:i'),
                'status' => ContentBatchStatus::Queued,
                'total_items' => $rawNewsItems->count(),
                'processed_items' => 0,
                'failed_items' => 0,
                'settings' => [
                    'automatic_pipeline' => true,
                    'news_source_id' => $newsSourceId,
                    'origin_label' => $originLabel,
                    'prompt_snapshot' => [
                        'name' => $prompt->name,
                        'version' => $prompt->version,
                        'tone' => $prompt->tone->value,
                        'language' => $prompt->language,
                        'target_length' => $prompt->target_length,
                        'temperature' => $prompt->temperature,
                        'system_prompt' => $prompt->system_prompt,
                        'user_prompt_template' => $prompt->user_prompt_template,
                    ],
                ],
            ]);

            $batch->items()->createMany($rawNewsItems->map(fn (RawNewsItem $item): array => [
                'raw_news_item_id' => $item->id,
                'status' => ContentBatchItemStatus::Queued,
            ])->all());

            RawNewsItem::query()
                ->whereKey($rawNewsItems->modelKeys())
                ->update(['status' => RawNewsStatus::Queued, 'failure_message' => null]);

            return $batch;
        }, 3);

        if ($batch) {
            ProcessContentBatch::dispatch($batch->id)->onQueue('content')->afterCommit();
        }

        return $batch;
    }

    private function creator(int $agencyId, ?int $preferredCreatorId): ?User
    {
        if ($preferredCreatorId) {
            $creator = User::query()->whereKey($preferredCreatorId)->where('is_active', true)->first();

            if ($creator) {
                return $creator;
            }
        }

        return User::query()
            ->where('agency_id', $agencyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first()
            ?? User::query()->where('is_active', true)->orderBy('id')->first();
    }

    private function prompt(int $agencyId): AiPrompt
    {
        $prompt = AiPrompt::query()
            ->where('is_active', true)
            ->where(function ($query) use ($agencyId): void {
                $query->where('agency_id', $agencyId)->orWhereNull('agency_id');
            })
            ->orderByDesc('agency_id')
            ->orderByDesc('version')
            ->orderBy('id')
            ->first();

        if ($prompt) {
            return $prompt;
        }

        return AiPrompt::query()->create([
            'agency_id' => $agencyId,
            'name' => 'Otomatik SEO Haber Editörü',
            'category' => 'haber',
            'tone' => PromptTone::Neutral,
            'language' => 'tr',
            'target_length' => 700,
            'temperature' => 0.7,
            'system_prompt' => 'Tam haber gövdesindeki doğrulanabilir ayrıntılara bağlı, özgün, tutarlı ve kurumsal ajans dilinde Türkçe haber üret.',
            'user_prompt_template' => 'Kişi, kurum, yer, tarih ve sayıları koruyarak; kaynak adı veya bağlantısı vermeden ters piramit yapısında haberleştir: {content}',
            'is_active' => true,
            'version' => 1,
        ]);
    }
}
