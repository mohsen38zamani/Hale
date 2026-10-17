<?php

namespace App\Domains\Creative\Services;

/**
 * The catalog-white pipeline: the product's own pixels resting on pure
 * #FFFFFF under a soft grounding shadow, composed with GD.
 *
 * No model stands between the product and the page. The cutout is pasted
 * without a single pixel of color change - no re-lighting, no redraw, no
 * upscaling - and the only thing drawn is the shadow that grounds it,
 * because a cutout floating on white reads as a sticker while the same
 * cutout on a shadow reads as an object on a shelf.
 */
class ProductCompositor
{
    /** The catalog square, in pixels. */
    private const CANVAS = 1600;

    /** The share of the canvas the product may occupy; the rest is white. */
    private const FILL = 0.78;

    /** The grounding shadow, as shares of the canvas. */
    private const SHADOW_WIDTH = 0.52;

    private const SHADOW_HEIGHT = 0.055;

    private const SHADOW_OFFSET = 0.012;

    /** The shadow pool's darkest gray: a 20/255 core at 42% over white. */
    private const SHADOW_CORE = 156;

    /**
     * Composite a product photo (ideally a transparent cutout) onto the
     * pure white catalog frame. Anything GD cannot read is refused loudly:
     * the caller decides whether to fall back to the original photo.
     */
    public function composite(string $contents): string
    {
        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw new \RuntimeException('تصویر محصول قابل خواندن نیست.');
        }

        // Palette PNGs carry their alpha in a tRNS chunk; flattening to
        // truecolor first is what makes the soft cutout edges blend into
        // the white instead of stepping over it.
        imagepalettetotruecolor($source);
        imagealphablending($source, false);
        imagesavealpha($source, true);

        $width = imagesx($source);
        $height = imagesy($source);
        $maxProduct = (int) round(self::CANVAS * self::FILL);
        $scale = min($maxProduct / $width, $maxProduct / $height, 1.0);

        $productWidth = max(1, (int) round($width * $scale));
        $productHeight = max(1, (int) round($height * $scale));
        $x = (int) round((self::CANVAS - $productWidth) / 2);
        // The product sits a touch above center: the shadow needs the room
        // under it, and a catalog eye reads the pair from there.
        $y = (int) round((self::CANVAS - $productHeight) / 2 - self::CANVAS * 0.02);

        $flat = imagecreatetruecolor(self::CANVAS, self::CANVAS);
        imagefilledrectangle($flat, 0, 0, self::CANVAS, self::CANVAS, imagecolorallocate($flat, 255, 255, 255));

        // The shadow is its own layer: a gray ellipse blurred twice on
        // white, then copied pixel for pixel. Copying is the point - a
        // merge pass would nudge the untouched white floor a single step
        // off #FFFFFF (GD blends in float), while a copy keeps the floor
        // exactly pure and the pool exactly as blurred.
        $shadow = imagecreatetruecolor(self::CANVAS, self::CANVAS);
        imagefilledrectangle($shadow, 0, 0, self::CANVAS, self::CANVAS, imagecolorallocate($shadow, 255, 255, 255));
        imagefilledellipse(
            $shadow,
            (int) round($x + $productWidth / 2),
            (int) round($y + $productHeight + self::CANVAS * self::SHADOW_OFFSET),
            (int) round(self::CANVAS * self::SHADOW_WIDTH),
            max(2, (int) round(self::CANVAS * self::SHADOW_HEIGHT)),
            imagecolorallocate($shadow, self::SHADOW_CORE, self::SHADOW_CORE, self::SHADOW_CORE)
        );
        imagefilter($shadow, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($shadow, IMG_FILTER_GAUSSIAN_BLUR);
        imagecopy($flat, $shadow, 0, 0, 0, 0, self::CANVAS, self::CANVAS);

        // The product itself: alpha-aware paste, exact pixels wherever it
        // lands. A photo that already fits is not resampled at all.
        imagealphablending($flat, true);
        imagecopyresampled($flat, $source, $x, $y, 0, 0, $productWidth, $productHeight, $width, $height);

        ob_start();
        imagepng($flat);
        $composited = (string) ob_get_clean();

        imagedestroy($shadow);
        imagedestroy($flat);
        imagedestroy($source);

        return $composited;
    }
}
