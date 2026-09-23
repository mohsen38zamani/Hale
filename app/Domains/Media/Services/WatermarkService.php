<?php

namespace App\Domains\Media\Services;

use RuntimeException;

class WatermarkService
{
    public function applyForPlan(string $contents, string $mime, string $planKey): string
    {
        if ($planKey !== 'free') {
            return $contents;
        }

        if (str_starts_with($mime, 'video/')) {
            return $contents;
        }

        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('پردازش watermark روی سرور فعال نیست.');
        }

        $image = imagecreatefromstring($contents);
        if ($image === false) {
            throw new RuntimeException('تصویر برای watermark معتبر نیست.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $color = imagecolorallocatealpha($image, 255, 255, 255, 35);
        imagefilledrectangle($image, 0, max(0, $height - 28), $width, $height, imagecolorallocatealpha($image, 0, 0, 0, 65));
        imagestring($image, 3, max(8, $width - 90), max(4, $height - 22), 'HALE FREE', $color);

        ob_start();
        $written = match ($mime) {
            'image/jpeg' => imagejpeg($image, null, 90),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, 85) : false,
            default => imagepng($image),
        };
        $watermarked = ob_get_clean();
        imagedestroy($image);

        if (! $written || $watermarked === false || $watermarked === '') {
            throw new RuntimeException('ساخت watermark ناموفق بود.');
        }

        return $watermarked;
    }
}
