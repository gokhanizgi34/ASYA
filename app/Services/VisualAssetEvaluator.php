<?php

namespace App\Services;

use App\CopyrightStatus;
use App\Models\VisualAsset;
use App\VisualAssetStatus;
use App\VisualSourceType;
use Illuminate\Support\Carbon;

class VisualAssetEvaluator
{
    /**
     * @return array{status: VisualAssetStatus, quality_score: int, evaluation_notes: string, evaluated_at: Carbon|null}
     */
    public function evaluate(VisualAsset $asset): array
    {
        if ($asset->source_type === VisualSourceType::AiGenerated && ! $asset->hasPreview()) {
            return [
                'status' => VisualAssetStatus::Generating,
                'quality_score' => 0,
                'evaluation_notes' => 'Özgün görsel üretim isteği sağlayıcı kuyruğuna hazırlandı.',
                'evaluated_at' => null,
            ];
        }

        $score = 100;
        $notes = [];

        if ($asset->width === null || $asset->height === null) {
            $score -= 25;
            $notes[] = 'Görsel boyutları doğrulanamadı.';
        } else {
            if ($asset->width < 1280) {
                $score -= $asset->width < 640 ? 45 : 20;
                $notes[] = 'Genişlik 1280 piksel hedefinin altında.';
            }

            if ($asset->height < 720) {
                $score -= $asset->height < 360 ? 45 : 20;
                $notes[] = 'Yükseklik 720 piksel hedefinin altında.';
            }
        }

        if ($asset->copyright_status === CopyrightStatus::Unknown) {
            $score -= 25;
            $notes[] = 'Telif durumu doğrulanmalı.';
        } elseif ($asset->copyright_status === CopyrightStatus::Restricted) {
            $score = min($score, 10);
            $notes[] = 'Telif kısıtı nedeniyle bu görsel yayımlanamaz.';
        }

        $score = max(0, min(100, $score));
        $isApproved = $score >= 70 && $asset->copyright_status->isSafeForPublishing();

        if ($isApproved) {
            $notes[] = 'Görsel yayın kalite ve telif eşiklerini karşılıyor.';
        } else {
            $notes[] = 'Arşivden alternatif seçin veya özgün görsel üretimi başlatın.';
        }

        return [
            'status' => $isApproved ? VisualAssetStatus::Approved : VisualAssetStatus::NeedsReplacement,
            'quality_score' => $score,
            'evaluation_notes' => implode(' ', $notes),
            'evaluated_at' => now(),
        ];
    }
}
