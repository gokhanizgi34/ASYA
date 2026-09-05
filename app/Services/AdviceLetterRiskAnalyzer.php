<?php

namespace App\Services;

use App\AdviceRiskLevel;

class AdviceLetterRiskAnalyzer
{
    /** @return array{level: AdviceRiskLevel, flags: array<int, string>} */
    public function analyze(string $question): array
    {
        $flags = [];

        if (preg_match('/(?:\+?90\s*)?0?5\d{2}[\s.-]*\d{3}[\s.-]*\d{2}[\s.-]*\d{2}/u', $question) === 1) {
            $flags[] = 'phone';
        }
        if (filter_var($question, FILTER_VALIDATE_EMAIL) || preg_match('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/iu', $question) === 1) {
            $flags[] = 'email';
        }
        if (preg_match('/\b\d{11}\b/u', preg_replace('/\s+/', '', $question) ?? '') === 1) {
            $flags[] = 'identity_number';
        }
        if (preg_match('/intihar|kendime zarar|öldürmek|şiddet|acil tehlike/iu', $question) === 1) {
            $flags[] = 'critical_safety';
        }

        $level = in_array('critical_safety', $flags, true)
            ? AdviceRiskLevel::Critical
            : ($flags === [] ? AdviceRiskLevel::Low : AdviceRiskLevel::High);

        return ['level' => $level, 'flags' => $flags];
    }
}
