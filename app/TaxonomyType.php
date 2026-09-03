<?php

namespace App;

enum TaxonomyType: string
{
    case Category = 'category';
    case Tag = 'tag';

    public function label(): string
    {
        return match ($this) {
            self::Category => 'Kategori',
            self::Tag => 'Etiket',
        };
    }
}
