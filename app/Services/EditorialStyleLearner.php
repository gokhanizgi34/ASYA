<?php

namespace App\Services;

use Illuminate\Support\Str;

class EditorialStyleLearner
{
    /** @return array<int, string> */
    public function learn(string $sampleText, string $preferredTerms): array
    {
        $manual = preg_split('/[,;\r\n]+/u', $preferredTerms) ?: [];
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower(strip_tags($sampleText))) ?: [];
        $stopWords = array_flip(['acaba', 'ama', 'ancak', 'artık', 'bir', 'biz', 'bu', 'çok', 'da', 'daha', 'de', 'diye', 'en', 'gibi', 'hem', 'her', 'ile', 'için', 'ise', 'ki', 'mı', 'mi', 'mu', 'mü', 'ne', 'olan', 'olarak', 'oldu', 'sonra', 'şu', 've', 'veya', 'ya']);
        $frequent = collect($words)->filter(fn (string $word): bool => mb_strlen($word) >= 4 && ! isset($stopWords[$word]))->countBy()->sortDesc()->keys()->take(40);

        return collect($manual)->map(fn (string $term): string => Str::of($term)->squish()->limit(80, '')->toString())
            ->merge($frequent)->filter()->unique(fn (string $term): string => Str::lower($term))->take(60)->values()->all();
    }

    /** @return array<string, string> */
    public function replacements(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/u', $value) ?: [])->mapWithKeys(function (string $line): array {
            [$from, $to] = array_pad(preg_split('/\s*(?:=>|=)\s*/u', trim($line), 2) ?: [], 2, '');
            $from = Str::of($from)->squish()->limit(100, '')->toString();
            $to = Str::of($to)->squish()->limit(100, '')->toString();

            return $from !== '' && $to !== '' ? [$from => $to] : [];
        })->take(100)->all();
    }

    /** @return array<int, string> */
    public function terms(string $value): array
    {
        return collect(preg_split('/[,;\r\n]+/u', $value) ?: [])->map(fn (string $term): string => Str::of($term)->squish()->limit(100, '')->toString())
            ->filter()->unique(fn (string $term): string => Str::lower($term))->take(100)->values()->all();
    }
}
