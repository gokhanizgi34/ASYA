<?php

namespace Tests\Unit;

use App\Services\ArticleBodyFormatter;
use PHPUnit\Framework\TestCase;

class ArticleBodyFormatterTest extends TestCase
{
    public function test_normalizes_heading_hierarchy_and_blank_lines(): void
    {
        $body = "# Gövdedeki H1\n\n\nİlk paragraf.\n\n#### Atlanan alt başlık\n\nİkinci paragraf.\n\n## Yeni ana bölüm";

        $formatted = (new ArticleBodyFormatter)->normalizeMarkdown($body);

        $this->assertSame("## Gövdedeki H1\n\nİlk paragraf.\n\n### Atlanan alt başlık\n\nİkinci paragraf.\n\n## Yeni ana bölüm", $formatted);
    }

    public function test_renders_readable_html_without_a_second_h1(): void
    {
        $body = "# Ana bölüm\n\nİlk paragraf.\n\n### Alt bölüm\n\nİkinci paragraf.\n\n#### Ayrıntı\n\nSon paragraf.";

        $formatted = (new ArticleBodyFormatter)->toHtml($body);

        $this->assertStringNotContainsString('<h1', $formatted);
        $this->assertStringContainsString('<h2 style="margin:2rem 0 0.875rem;line-height:1.35">Ana bölüm</h2>', $formatted);
        $this->assertStringContainsString('<h3 style="margin:1.625rem 0 0.75rem;line-height:1.35">Alt bölüm</h3>', $formatted);
        $this->assertStringContainsString('<h4 style="margin:1.375rem 0 0.625rem;line-height:1.35">Ayrıntı</h4>', $formatted);
        $this->assertStringContainsString('<p style="margin:0 0 1.125rem;line-height:1.75">İlk paragraf.</p>', $formatted);
    }

    public function test_recovers_inline_headings_and_splits_long_legacy_paragraphs(): void
    {
        $body = 'İlk cümle. İkinci cümle. Üçüncü cümle. Dördüncü cümle. Beşinci cümle. ## Gizli Kamera İncelemeye Alındı Ekipler olay yerinde araştırma yaptı. İnceleme sürüyor.';

        $formatted = (new ArticleBodyFormatter)->normalizeMarkdown($body);

        $this->assertSame("İlk cümle. İkinci cümle. Üçüncü cümle.\n\nDördüncü cümle. Beşinci cümle.\n\n## Gizli Kamera İncelemeye Alındı\n\nEkipler olay yerinde araştırma yaptı. İnceleme sürüyor.", $formatted);
    }
}
