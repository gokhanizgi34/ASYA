<?php

namespace App\Services;

use App\Models\Article;
use App\Models\SocialPublishingAccount;
use Illuminate\Support\Str;

class XPostComposer
{
    public function compose(Article $article, SocialPublishingAccount $account): string
    {
        $mention = $this->municipalityMention($article, $account);
        $suffix = $mention ? "\n\n".$mention : '';
        $titleLimit = max(80, 255 - mb_strlen($suffix));
        $title = Str::of(strip_tags($article->title))->squish()->limit($titleLimit, '')->toString();

        return $title.$suffix;
    }

    private function municipalityMention(Article $article, SocialPublishingAccount $account): ?string
    {
        $haystack = $this->normalize(implode(' ', [
            $article->title,
            $article->summary,
            $article->body,
            $article->source_name,
            $article->source_url,
        ]));
        $host = Str::lower((string) parse_url((string) $article->source_url, PHP_URL_HOST));
        $isMunicipalityNews = str_contains($haystack, 'belediye') || str_ends_with($host, '.bel.tr');

        if (! $isMunicipalityNews) {
            return null;
        }

        $configuredHandle = collect($account->mention_rules ?? [])
            ->filter(fn (mixed $handle, mixed $institution): bool => is_string($institution) && is_string($handle))
            ->sortByDesc(fn (string $handle, string $institution): int => mb_strlen($institution))
            ->first(fn (string $handle, string $institution): bool => str_contains($haystack, $this->normalize($institution)));

        if (is_string($configuredHandle) && $this->isDifferentAccount($configuredHandle, $account->account_handle)) {
            return '@'.ltrim($configuredHandle, '@');
        }

        $sourceHandle = $this->sourceHandle($article);

        return $sourceHandle && $this->isDifferentAccount($sourceHandle, $account->account_handle)
            ? $sourceHandle
            : null;
    }

    private function sourceHandle(Article $article): ?string
    {
        $sourceUrl = (string) $article->source_url;
        $host = Str::lower((string) parse_url($sourceUrl, PHP_URL_HOST));
        $path = (string) parse_url($sourceUrl, PHP_URL_PATH);

        if (! in_array($host, ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'], true)
            || preg_match('~^/([A-Za-z0-9_]{1,15})/status/\d+~', $path, $matches) !== 1) {
            return null;
        }

        $sourceIdentity = Str::of((string) $article->source_name)
            ->replaceMatches('/^(?:X\s+|X\s+HABER\s*)/iu', '')
            ->replaceMatches('/\b(?:resmi|official|hesabı|account)\b/iu', ' ')
            ->squish()
            ->toString();

        if (mb_strlen($sourceIdentity) < 4) {
            return null;
        }

        $articleText = $this->normalize($article->title.' '.$article->summary.' '.$article->body);

        return str_contains($articleText, $this->normalize($sourceIdentity)) ? '@'.$matches[1] : null;
    }

    private function isDifferentAccount(string $candidate, string $publishingAccount): bool
    {
        return Str::lower(ltrim($candidate, '@')) !== Str::lower(ltrim($publishingAccount, '@'));
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->stripTags()
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
}
