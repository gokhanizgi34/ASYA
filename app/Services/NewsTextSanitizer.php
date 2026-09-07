<?php

namespace App\Services;

use Illuminate\Support\Str;

class NewsTextSanitizer
{
    /** @var array<int, string> */
    private const BOILERPLATE_START_PATTERNS = [
        '/\bSitemizde kullanıcı deneyimini geliştirmek\b/iu',
        '/\binternet sitesinin verimli çalışmasını sağlamak amacıyla çerezler\b/iu',
        '/\b(?:bu|web) (?:web |internet )?sitesi(?:nde)? (?:size daha iyi hizmet sunmak|çerez(?:ler)? kullanmak)\b/iu',
        '/\bÇerez Bildirimi,? Gizlilik Bildiriminin bir parçasıdır\b/iu',
        '/\b(?:we use cookies|this website uses cookies)\b/iu',
    ];

    public function clean(string $body): string
    {
        $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $body);
        $body = preg_replace('/[^\S\n]+/u', ' ', $body) ?? $body;
        $body = preg_replace('/\n{3,}/u', "\n\n", $body) ?? $body;
        $body = $this->removeBoilerplateSuffix($body);

        $paragraphs = collect(preg_split('/\n{2,}/u', $body) ?: [])
            ->map(fn (string $paragraph): string => Str::squish($paragraph))
            ->reject(fn (string $paragraph): bool => $this->isBoilerplateParagraph($paragraph))
            ->filter();

        return trim($paragraphs->implode("\n\n"));
    }

    private function removeBoilerplateSuffix(string $body): string
    {
        $firstOffset = null;

        foreach (self::BOILERPLATE_START_PATTERNS as $pattern) {
            if (preg_match($pattern, $body, $matches, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $offset = $matches[0][1];
            $firstOffset = $firstOffset === null ? $offset : min($firstOffset, $offset);
        }

        return $firstOffset === null ? $body : substr($body, 0, $firstOffset);
    }

    private function isBoilerplateParagraph(string $paragraph): bool
    {
        return preg_match('/^(?:çerezleri (?:kabul et|reddet|yönet)|çerez (?:ayarları|tercihleri|bildirimi)|gizlilik bildirimi|tümünü kabul et|cookie (?:settings|preferences)|accept all cookies)\b/iu', $paragraph) === 1;
    }
}
