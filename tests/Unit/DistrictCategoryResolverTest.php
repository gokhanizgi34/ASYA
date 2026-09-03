<?php

namespace Tests\Unit;

use App\Services\DistrictCategoryResolver;
use PHPUnit\Framework\TestCase;

class DistrictCategoryResolverTest extends TestCase
{
    public function test_it_resolves_turkish_district_names_from_source_and_story_text(): void
    {
        $resolver = new DistrictCategoryResolver;

        $this->assertSame('Pendik', $resolver->resolveText('Pendik Belediyesi sahil düzenlemesini duyurdu.'));
        $this->assertSame('Ümraniye', $resolver->resolveText('https://umraniye.bel.tr/haber/yeni-park'));
        $this->assertSame('Çekmeköy', $resolver->resolveText('ÇEKMEKÖY ilçesinde yeni merkez açıldı.'));
    }

    public function test_it_returns_null_when_no_istanbul_district_is_present(): void
    {
        $this->assertNull((new DistrictCategoryResolver)->resolveText('Türkiye ekonomisine ilişkin genel değerlendirme yayımlandı.'));
    }
}
