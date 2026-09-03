<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Services\SeoAnalyzer;
use Tests\TestCase;

class SeoAnalyzerTest extends TestCase
{
    public function test_analyzer_reports_problems_for_thin_content(): void
    {
        $article = new Article([
            'title' => 'Kısa Haber',
            'summary' => 'Kısa özet.',
            'body' => 'Bu oldukça kısa bir haber metnidir.',
        ]);

        $result = (new SeoAnalyzer)->analyze($article, 'ekonomi');

        $this->assertLessThan(60, $result['score']);
        $this->assertContains('Haber metni kısa.', $result['issues']);
        $this->assertContains('Odak anahtar kelime başlıkta bulunmuyor.', $result['issues']);
        $this->assertLessThan(10, $result['word_count']);
    }

    public function test_analyzer_produces_high_score_metadata_keywords_and_hashtags(): void
    {
        $sentences = [];

        for ($index = 0; $index < 40; $index++) {
            $focus = $index < 5 ? 'dijital yayıncılık ' : '';
            $sentences[] = $focus.'sektör gelişmeleri uzman görüşleri veriler ışığında okurlara açık biçimde aktarıldı.';
        }

        $article = new Article([
            'title' => 'Dijital Yayıncılık Sektöründe Yeni Dönem Başlıyor',
            'summary' => str_repeat('Dijital medya ekosistemindeki yeni gelişmeler ve uzman değerlendirmeleri ayrıntılı biçimde ele alındı. ', 2),
            'body' => "# Genel Bakış\n".implode(' ', $sentences),
        ]);

        $result = (new SeoAnalyzer)->analyze($article, 'dijital yayıncılık');

        $this->assertGreaterThanOrEqual(80, $result['score']);
        $this->assertSame('dijital yayıncılık', $result['focus_keyword']);
        $this->assertNotEmpty($result['keywords']);
        $this->assertNotEmpty($result['hashtags']);
        $this->assertGreaterThan(300, $result['word_count']);
        $this->assertBetween(0.5, 3.0, $result['keyword_density']);
    }

    private function assertBetween(float $minimum, float $maximum, float $actual): void
    {
        $this->assertGreaterThanOrEqual($minimum, $actual);
        $this->assertLessThanOrEqual($maximum, $actual);
    }
}
