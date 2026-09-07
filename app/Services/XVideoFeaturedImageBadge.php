<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class XVideoFeaturedImageBadge
{
    public function apply(string $bytes, string $sourceUrl): string
    {
        if (! $this->isXVideoUrl($sourceUrl) || ! extension_loaded('gd')) {
            return $bytes;
        }

        try {
            $image = @imagecreatefromstring($bytes);

            if ($image === false) {
                return $bytes;
            }

            $width = imagesx($image);
            $height = imagesy($image);

            if ($width < 320 || $height < 180) {
                imagedestroy($image);

                return $bytes;
            }

            imagealphablending($image, true);
            imagesavealpha($image, true);

            $fontPath = $this->fontPath();
            $fontSize = max(18, min(58, (int) round($width / 28)));
            $label = $fontPath ? 'VİDEO HABER' : 'VIDEO HABER';
            $padding = max(12, (int) round($fontSize * 0.55));
            $iconWidth = max(18, (int) round($fontSize * 0.72));
            $textWidth = $fontPath
                ? $this->textWidth($label, $fontPath, $fontSize)
                : imagefontwidth(5) * strlen($label);
            $badgeWidth = $padding + $iconWidth + $padding + $textWidth + $padding;
            $badgeHeight = max(48, (int) round($fontSize * 1.75));
            $margin = max(14, (int) round($width * 0.02));
            $left = max(0, $width - $badgeWidth - $margin);
            $top = min($height - $badgeHeight, $margin);
            $right = min($width - 1, $left + $badgeWidth);
            $bottom = min($height - 1, $top + $badgeHeight);
            $red = imagecolorallocatealpha($image, 205, 24, 35, 8);
            $white = imagecolorallocate($image, 255, 255, 255);

            imagefilledrectangle($image, $left, $top, $right, $bottom, $red);

            $iconLeft = $left + $padding;
            $iconCenterY = (int) round(($top + $bottom) / 2);
            imagefilledpolygon($image, [
                $iconLeft, $iconCenterY - (int) round($iconWidth * 0.65),
                $iconLeft, $iconCenterY + (int) round($iconWidth * 0.65),
                $iconLeft + $iconWidth, $iconCenterY,
            ], $white);

            $textX = $iconLeft + $iconWidth + $padding;
            if ($fontPath) {
                $textY = $iconCenterY + (int) round($fontSize * 0.38);
                imagettftext($image, $fontSize, 0, $textX, $textY, $white, $fontPath, $label);
            } else {
                $textY = $iconCenterY - (int) round(imagefontheight(5) / 2);
                imagestring($image, 5, $textX, $textY, $label, $white);
            }

            $result = $this->encode($image, $bytes);
            imagedestroy($image);

            return $result;
        } catch (Throwable $exception) {
            Log::warning('X video manşet etiketi oluşturulamadı; orijinal görsel kullanılacak.', [
                'source_url' => $sourceUrl,
                'message' => $exception->getMessage(),
            ]);

            return $bytes;
        }
    }

    public function isXVideoUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        return in_array($host, ['x.com', 'www.x.com', 'twitter.com', 'www.twitter.com'], true)
            && preg_match('~^/[A-Za-z0-9_]+/status/\d+/video/\d+/?$~', $path) === 1;
    }

    private function fontPath(): ?string
    {
        foreach ([
            base_path('resources/fonts/DejaVuSans-Bold.ttf'),
            'C:\\Windows\\Fonts\\arialbd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        ] as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function textWidth(string $text, string $fontPath, int $fontSize): int
    {
        $bounds = imagettfbbox($fontSize, 0, $fontPath, $text);

        return is_array($bounds) ? abs($bounds[2] - $bounds[0]) : 0;
    }

    private function encode(\GdImage $image, string $fallback): string
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
