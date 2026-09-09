<?php

namespace Tests\Unit;

use App\Services\HeadlineImageFormatter;
use GdImage;
use Tests\TestCase;

class HeadlineImageFormatterTest extends TestCase
{
    public function test_portrait_image_is_preserved_inside_a_125_to_65_headline_canvas(): void
    {
        $original = $this->png(420, 900);

        $result = app(HeadlineImageFormatter::class)->format($original);

        $this->assertNotSame($original, $result);
        $image = imagecreatefromstring($result);
        $this->assertInstanceOf(GdImage::class, $image);
        $this->assertSame(1250, imagesx($image));
        $this->assertSame(650, imagesy($image));
        $center = imagecolorsforindex($image, imagecolorat($image, 625, 325));
        $this->assertSame(20, $center['red']);
        $this->assertSame(40, $center['green']);
        $this->assertSame(80, $center['blue']);
        imagedestroy($image);
    }

    public function test_invalid_image_bytes_are_not_changed(): void
    {
        $invalid = 'not-an-image';

        $result = app(HeadlineImageFormatter::class)->format($invalid);

        $this->assertSame($invalid, $result);
    }

    public function test_already_formatted_image_is_not_reencoded(): void
    {
        $formatted = $this->png(1250, 650);

        $result = app(HeadlineImageFormatter::class)->format($formatted);

        $this->assertSame($formatted, $result);
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 20, 40, 80);
        imagefill($image, 0, 0, $background);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return is_string($bytes) ? $bytes : '';
    }
}
