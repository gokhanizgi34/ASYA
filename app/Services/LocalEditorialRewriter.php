<?php

namespace App\Services;

use App\Models\Article;
use App\Models\EditorialStyleProfile;
use App\Models\RawNewsItem;
use Illuminate\Support\Str;

class LocalEditorialRewriter
{
    /**
     * @return array{title:string,summary:string,body:string,focus_keyword:string,keywords:array<int,string>,hashtags:array<int,string>,category:string,ai_provider:null,editorial_engine:string,destination:string,style_profile_id:int}|null
     */
    public function rewrite(RawNewsItem $rawNewsItem): ?array
    {
        $profile = EditorialStyleProfile::query()->where('agency_id', $rawNewsItem->agency_id)->where('is_active', true)->first();
        if (! $profile || $profile->daily_quota === 0 || $this->quotaUsed($profile) >= $profile->daily_quota) {
            return null;
        }

        $title = Str::of(strip_tags($rawNewsItem->original_title))->squish()->toString();
        $body = Str::of(html_entity_decode(strip_tags($rawNewsItem->original_body), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->replaceMatches('/https?:\/\/\S+/iu', '')->replaceMatches('/\s+/u', ' ')->trim()->toString();
        $changes = 0;
        foreach ($profile->replacements ?? [] as $from => $to) {
            $before = $title."\n".$body;
            $title = str_ireplace((string) $from, (string) $to, $title);
            $body = str_ireplace((string) $from, (string) $to, $body);
            $changes += $before !== $title."\n".$body ? 1 : 0;
        }
        foreach ($profile->forbidden_terms ?? [] as $term) {
            $body = str_ireplace((string) $term, '', $body);
        }
        if ($changes < 2 || Str::length($body) < 600) {
            return null;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $body) ?: [];
        $paragraphs = collect($sentences)->filter(fn (string $sentence): bool => Str::length(trim($sentence)) >= 20)
            ->chunk(2)->map(fn ($chunk): string => trim($chunk->implode(' ')))->filter()->implode("\n\n");
        if (Str::length($paragraphs) < 500) {
            return null;
        }

        $terms = collect($profile->learned_terms ?? [])->filter(fn (string $term): bool => Str::contains(Str::lower($title.' '.$paragraphs), Str::lower($term)))->take(8)->values()->all();

        return [
            'title' => Str::limit($title, 220, ''), 'summary' => Str::limit($paragraphs, 155, ''), 'body' => $paragraphs,
            'focus_keyword' => $terms[0] ?? Str::lower(Str::words($title, 4, '')), 'keywords' => $terms,
            'hashtags' => collect($terms)->take(5)->map(fn (string $term): string => '#'.Str::studly($term))->all(),
            'category' => 'Gündem', 'ai_provider' => null, 'editorial_engine' => 'local_style_memory',
            'destination' => $profile->destination, 'style_profile_id' => $profile->id,
        ];
    }

    private function quotaUsed(EditorialStyleProfile $profile): int
    {
        return Article::query()->where('agency_id', $profile->agency_id)->whereDate('created_at', today())
            ->where('editorial_metadata->style_profile_id', $profile->id)->count();
    }
}
