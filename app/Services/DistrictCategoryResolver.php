<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Str;

class DistrictCategoryResolver
{
    /** @var array<int, string> */
    private const ISTANBUL_DISTRICTS = [
        'Adalar', 'Arnavutköy', 'Ataşehir', 'Avcılar', 'Bağcılar', 'Bahçelievler', 'Bakırköy', 'Başakşehir', 'Bayrampaşa', 'Beşiktaş', 'Beykoz', 'Beylikdüzü', 'Beyoğlu', 'Büyükçekmece', 'Çatalca', 'Çekmeköy', 'Esenler', 'Esenyurt', 'Eyüpsultan', 'Fatih', 'Gaziosmanpaşa', 'Güngören', 'Kadıköy', 'Kağıthane', 'Kartal', 'Küçükçekmece', 'Maltepe', 'Pendik', 'Sancaktepe', 'Sarıyer', 'Silivri', 'Sultanbeyli', 'Sultangazi', 'Şile', 'Şişli', 'Tuzla', 'Ümraniye', 'Üsküdar', 'Zeytinburnu',
    ];

    public function resolve(Article $article): ?string
    {
        return $this->resolveText(implode(' ', [
            $article->source_name,
            $article->source_url,
            $article->title,
            $article->summary,
            Str::limit(strip_tags($article->body), 1500, ''),
        ]));
    }

    public function resolveText(string $text): ?string
    {
        $haystack = ' '.Str::of($text)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString().' ';

        foreach (self::ISTANBUL_DISTRICTS as $district) {
            $needle = ' '.Str::of($district)->ascii()->lower()->toString().' ';

            if (str_contains($haystack, $needle)) {
                return $district;
            }
        }

        return null;
    }
}
