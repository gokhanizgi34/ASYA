<?php

namespace App\Services;

use App\AdviceRiskLevel;
use Illuminate\Support\Str;

class AdviceSafetyAnalyzer
{
    /** @return array{risk_level: AdviceRiskLevel, flags: array<int, string>} */
    public function analyze(string $question): array
    {
        $normalized = Str::lower(strip_tags($question));
        $flags = [];

        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $question) === 1) {
            $flags[] = 'email';
        }

        if (preg_match('/(?:\+?90\s?)?(?:0?5\d{2})[\s.-]?\d{3}[\s.-]?\d{2}[\s.-]?\d{2}/', $question) === 1) {
            $flags[] = 'phone';
        }

        if (preg_match('/TR[0-9]{24}/i', preg_replace('/\s+/', '', $question) ?? $question) === 1) {
            $flags[] = 'iban';
        }

        if (Str::contains($normalized, ['intihar', 'kendimi öldür', 'canıma kıy', 'tecavüz', 'çocuk istismarı', 'öldürmek istiyorum'])) {
            $flags[] = 'immediate_safety';
        }

        if (Str::contains($normalized, ['şiddet', 'istismar', 'tehdit', 'takip ediliyorum'])) {
            $flags[] = 'abuse_or_violence';
        }

        if (Str::contains($normalized, ['ilaç', 'teşhis', 'dava', 'mahkeme', 'avukat'])) {
            $flags[] = 'professional_advice';
        }

        $riskLevel = match (true) {
            in_array('immediate_safety', $flags, true) => AdviceRiskLevel::Critical,
            in_array('abuse_or_violence', $flags, true) => AdviceRiskLevel::High,
            array_intersect($flags, ['email', 'phone', 'iban']) !== [] => AdviceRiskLevel::High,
            in_array('professional_advice', $flags, true) => AdviceRiskLevel::Medium,
            default => AdviceRiskLevel::Low,
        };

        return ['risk_level' => $riskLevel, 'flags' => array_values(array_unique($flags))];
    }
}
