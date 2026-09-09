<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\Log;
use Throwable;

class HeadlineImageFormatter
{
    public const WIDTH = 1250;

    public const HEIGHT = 650;

    public function format(string $bytes): string
    {
        if (! extension_loaded('gd')) {
            return $bytes;
        }

        try {
            $imageInfo = @getimagesizefromstring($bytes);
            $sourceWidth = is_array($imageInfo) ? (int) ($imageInfo[0] ?? 0) : 0;
            $sourceHeight = is_array($imageInfo) ? (int) ($imageInfo[1] ?? 0) : 0;

            if ($sourceWidth < 1 || $sourceHeight < 1 || $sourceHeight > intdiv(40_000_000, $sourceWidth)) {
                return $bytes;
            }

            $source = @imagecreatefromstring($bytes);

            if (! $source instanceof GdImage) {
                return $bytes;
            }

            if (imagesx($source) === self::WIDTH && imagesy($source) === self::HEIGHT) {
                imagedestroy($source);

                return $bytes;
            }

            $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
            $this->paintBackground($canvas, $source);
            $this->paintForeground($canvas, $source);
            $result = $this->encode($canvas, $bytes);
            imagedestroy($canvas);
            imagedestroy($source);

            return $result;
        } catch (Throwable $exception) {
            Log::warning('Manşet görseli 125:65 oranına dönüştürülemedi; orijinal görsel korunacak.', [
                'message' => $exception->getMessage(),
            ]);

            return $bytes;
        }
    }

    private function paintBackground(GdImage $canvas, GdImage $source): void
    {
        [$sourceX, $sourceY, $sourceWidth, $sourceHeight] = $this->coverCrop($source);
        imagecopyresampled($canvas, $source, 0, 0, $sourceX, $sourceY, self::WIDTH, self::HEIGHT, $sourceWidth, $sourceHeight);

        for ($pass = 0; $pass < 4; $pass++) {
            imagefilter($canvas, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $shade = imagecolorallocatealpha($canvas, 4, 12, 28, 58);
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, $shade);
    }

    private function paintForeground(GdImage $canvas, GdImage $source): void
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $maximumWidth = self::WIDTH - 32;
        $maximumHeight = self::HEIGHT - 32;
        $scale = min($maximumWidth / $sourceWidth, $maximumHeight / $sourceHeight);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $left = (int) floor((self::WIDTH - $width) / 2);
        $top = (int) floor((self::HEIGHT - $height) / 2);

        imagecopyresampled($canvas, $source, $left, $top, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        $border = imagecolorallocatealpha($canvas, 255, 255, 255, 58);
        imagerectangle($canvas, $left, $top, $left + $width - 1, $top + $height - 1, $border);
    }

    /** @return array{int, int, int, int} */
    private function coverCrop(GdImage $source): array
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = self::WIDTH / self::HEIGHT;

        if ($sourceRatio > $targetRatio) {
            $cropWidth = (int) round($sourceHeight * $targetRatio);

            return [(int) floor(($sourceWidth - $cropWidth) / 2), 0, $cropWidth, $sourceHeight];
        }

        $cropHeight = (int) round($sourceWidth / $targetRatio);

        return [0, (int) floor(($sourceHeight - $cropHeight) / 2), $sourceWidth, $cropHeight];
    }

    private function encode(GdImage $image, string $fallback): string
    {
        $info = @getimagesizefromstring($fallback);
        $mimeType = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        ob_start();
        $encoded = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, null, 90),
            'image/png' => imagepng($image, null, 6),
            'image/webp' => function_exists('imagewebp') && imagewebp($image, null, 90),
            default => false,
        };
        $result = ob_get_clean();

        return $encoded && is_string($result) && $result !== '' ? $result : $fallback;
    }
}
