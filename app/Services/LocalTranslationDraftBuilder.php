<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Str;

class LocalTranslationDraftBuilder
{
    /** @return array{source_checksum: string, title: string, summary: ?string, body: string} */
    public function build(Article $article, string $targetLocale): array
    {
        $language = match ($targetLocale) {
            'en' => 'English',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'es' => 'Español',
            'ar' => 'العربية',
            'ru' => 'Русский',
            default => strtoupper($targetLocale),
        };
        $notice = "[{$language} editoryal çeviri taslağı — yayımdan önce insan çevirisi ve doğrulaması zorunludur]";

        return [
            'source_checksum' => $this->checksum($article),
            'title' => Str::limit("{$notice} {$article->title}", 255, ''),
            'summary' => $article->summary ? "{$notice}\n\n{$article->summary}" : null,
            'body' => "{$notice}\n\n{$article->body}",
        ];
    }

    public function checksum(Article $article): string
    {
        return hash('sha256', implode("\n", [$article->title, $article->summary, $article->body]));
    }
}
