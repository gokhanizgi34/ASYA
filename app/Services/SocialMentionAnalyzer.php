<?php

namespace App\Services;

use App\Models\SocialListeningWatch;
use App\SocialSentiment;
use Illuminate\Support\Str;

class SocialMentionAnalyzer
{
    /** @var array<int, string> */
    private const POSITIVE_TERMS = ['başarılı', 'güvenilir', 'harika', 'teşekkür', 'beğendim', 'iyi'];

    /** @var array<int, string> */
    private const NEGATIVE_TERMS = ['hata', 'şikayet', 'yanlış', 'kötü', 'sorun', 'yalan'];

    /** @var array<int, string> */
    private const URGENT_TERMS = ['acil', 'tehlike', 'kriz', 'dolandırıcılık', 'dava', 'boykot'];

    /** @return array{sentiment: SocialSentiment, sentiment_score: float, urgency_score: int, matched_keywords: array<int, string>} */
    public function analyze(SocialListeningWatch $watch, string $content, int $engagementCount): array
    {
        $normalizedContent = Str::lower($content);
        $excluded = collect($watch->excluded_terms ?? [])->contains(
            fn (string $term): bool => filled($term) && Str::contains($normalizedContent, Str::lower($term))
        );

        $matchedKeywords = $excluded
            ? []
            : collect($watch->keywords)
                ->filter(fn (string $keyword): bool => filled($keyword) && Str::contains($normalizedContent, Str::lower($keyword)))
                ->values()
                ->all();

        $positiveCount = $this->termCount($normalizedContent, self::POSITIVE_TERMS);
        $negativeCount = $this->termCount($normalizedContent, self::NEGATIVE_TERMS);
        $balance = $positiveCount - $negativeCount;
        $total = max(1, $positiveCount + $negativeCount);
        $sentimentScore = round(max(-1, min(1, $balance / $total)), 3);
        $sentiment = match (true) {
            $sentimentScore > 0.15 => SocialSentiment::Positive,
            $sentimentScore < -0.15 => SocialSentiment::Negative,
            default => SocialSentiment::Neutral,
        };

        $urgentCount = $this->termCount($normalizedContent, self::URGENT_TERMS);
        $engagementWeight = min(30, (int) floor(log10(max(1, $engagementCount + 1)) * 10));
        $negativeWeight = $sentiment === SocialSentiment::Negative ? 20 : 0;
        $urgencyScore = min(100, ($urgentCount * 25) + $engagementWeight + $negativeWeight);

        return compact('sentiment', 'sentimentScore', 'urgencyScore', 'matchedKeywords');
    }

    /** @param array<int, string> $terms */
    private function termCount(string $content, array $terms): int
    {
        return collect($terms)->sum(fn (string $term): int => Str::substrCount($content, $term));
    }
}
