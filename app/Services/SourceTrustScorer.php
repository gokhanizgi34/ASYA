<?php

namespace App\Services;

use App\SourceTrustBand;

class SourceTrustScorer
{
    /** @param array<string, int> $scores
     * @return array{score: float, band: SourceTrustBand}
     */
    public function score(array $scores): array
    {
        $weightedScore = round(
            ($scores['identity_transparency'] * 0.20)
            + ($scores['evidence_quality'] * 0.25)
            + ($scores['correction_policy'] * 0.15)
            + ($scores['historical_accuracy'] * 0.25)
            + ($scores['editorial_independence'] * 0.15),
            2,
        );

        $band = match (true) {
            $weightedScore >= 80 => SourceTrustBand::High,
            $weightedScore >= 60 => SourceTrustBand::Medium,
            $weightedScore >= 40 => SourceTrustBand::Caution,
            default => SourceTrustBand::Low,
        };

        return ['score' => $weightedScore, 'band' => $band];
    }
}
