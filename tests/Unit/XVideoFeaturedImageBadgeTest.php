<?php

namespace Tests\Unit;

use App\Services\XVideoFeaturedImageBadge;
use Tests\TestCase;

class XVideoFeaturedImageBadgeTest extends TestCase
{
    public function test_it_adds_a_red_video_news_badge_to_an_x_video_image(): void
    {
        $original = $this->png(800, 450);

        $result = app(XVideoFeaturedImageBadge::class)->apply(
            $original,
            'https://x.com/umraniyebeltr/status/2097004425772474609/video/1',
        );

        $this->assertNotSame($original, $result);
        $image = imagecreatefromstring($result);
        $this->assertInstanceOf(\GdImage::class, $image);
        $topColor = imagecolorsforindex($image, imagecolorat($image, 780, 20));
        $this->assertSame(20, $topColor['red']);
        $this->assertSame(40, $topColor['green']);
        $this->assertSame(80, $topColor['blue']);

        $color = imagecolorsforindex($image, imagecolorat($image, 400, 40));
        $this->assertGreaterThan(150, $color['red']);
        $this->assertLessThan(100, $color['green']);
        $this->assertLessThan(100, $color['blue']);
        imagedestroy($image);
    }

    public function test_it_does_not_change_a_normal_x_photo_image(): void
    {
        $original = $this->png(800, 450);

        $result = app(XVideoFeaturedImageBadge::class)->apply(
            $original,
            'https://x.com/alitombastr/status/2096851618465550436/photo/1',
        );

        $this->assertSame($original, $result);
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
