<?php

namespace App\Domains\Generations\Services;

/**
 * The master canvas (spec 5.2): the scene is rendered once and every channel
 * frame - 1:1 post, 4:5 feed, 9:16 story - is cut from that one master, so
 * three sizes never cost three model calls. Extraction is free pixels, not a
 * second render.
 *
 * The cut follows the subject rather than the frame edge. A product shot
 * composed wide and then sliced down the middle loses exactly the thing the
 * shot was made to show, so the crop window is anchored on the content
 * centroid: the sampled pixels that are not background (the corners say what
 * background is). A flat catalog square has no subject to follow and simply
 * stays centered.
 */
class MasterCanvasCropper
{
    /**
     * How far a pixel's channels may drift from the background and still
     * count as background. Summed over R, G and B.
     */
    private const BACKGROUND_DELTA = 60;

    /** Sampling step of the centroid pass; every 8th pixel is plenty. */
    private const SAMPLE_STEP = 8;

    /** Aspect distance under which a master already is the asked frame. */
    private const RATIO_EPSILON = 0.005;

    /** Re-encoding quality for the lossy formats. */
    private const QUALITY = 90;

    /**
     * Cut the channel frame for a width:height ratio out of the master.
     *
     * @param  array{w: int, h: int}  $ratio  from config `creative.master_canvas.ratios`
     * @param  int  $maxEdge  longest edge of the result; never upscales
     * @return array{bytes: string, mime: string}|null null when GD cannot read the master
     */
    public function frame(string $contents, string $mime, array $ratio, int $maxEdge): ?array
    {
        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            return null;
        }

        // Palette images carry their alpha in a tRNS chunk; truecolor first
        // is what makes a transparent corner read as background here and as
        // white in the encoded frame.
        imagepalettetotruecolor($source);

        $width = imagesx($source);
        $height = imagesy($source);
        $target = $ratio['w'] / $ratio['h'];

        // A master that already is the frame keeps its own bytes: no
        // resample, no recompression, no quality lost to a round trip. The
        // formats GD can write back are the ones allowed to skip encoding;
        // anything else has to pass through the encoder to become a frame.
        if (abs($width / $height - $target) <= self::RATIO_EPSILON
            && max($width, $height) <= $maxEdge
            && in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            imagedestroy($source);

            return ['bytes' => $contents, 'mime' => $mime];
        }

        // The largest rectangle of the wanted aspect that fits the master.
        if ($width / $height > $target) {
            $cropHeight = $height;
            $cropWidth = max(1, (int) round($height * $target));
        } else {
            $cropWidth = $width;
            $cropHeight = max(1, (int) round($width / $target));
        }

        [$anchorX, $anchorY] = $this->contentAnchor($source, $width, $height);
        $x = min(max(0, $anchorX - (int) round($cropWidth / 2)), $width - $cropWidth);
        $y = min(max(0, $anchorY - (int) round($cropHeight / 2)), $height - $cropHeight);

        $frame = imagecreatetruecolor($cropWidth, $cropHeight);
        // White floor under the paste, so a master with real transparency
        // lands on catalog white instead of on GD's default black.
        imagefilledrectangle($frame, 0, 0, $cropWidth, $cropHeight, imagecolorallocate($frame, 255, 255, 255));
        imagealphablending($frame, true);
        imagecopyresampled($frame, $source, 0, 0, $x, $y, $cropWidth, $cropHeight, $cropWidth, $cropHeight);

        // Down to the edge budget, never up: a crop of a 1K master is still
        // 1K of information and scaling it up would only invent pixels.
        if (max($cropWidth, $cropHeight) > $maxEdge) {
            $scale = $maxEdge / max($cropWidth, $cropHeight);
            $scaledWidth = max(1, (int) round($cropWidth * $scale));
            $scaledHeight = max(1, (int) round($cropHeight * $scale));
            $scaled = imagecreatetruecolor($scaledWidth, $scaledHeight);
            imagecopyresampled($scaled, $frame, 0, 0, 0, 0, $scaledWidth, $scaledHeight, $cropWidth, $cropHeight);
            imagedestroy($frame);
            $frame = $scaled;
        }

        [$bytes, $outMime] = $this->encode($frame, $mime);

        imagedestroy($frame);
        imagedestroy($source);

        return ['bytes' => $bytes, 'mime' => $outMime];
    }

    /**
     * The middle of the subject: the mean position of every sampled pixel
     * that is not background. Uniform frames fall back to their own centre.
     *
     * @return array{0: int, 1: int}
     */
    private function contentAnchor(\GdImage $image, int $width, int $height): array
    {
        [$backR, $backG, $backB] = $this->averageCorners($image, $width, $height);

        $sumX = 0;
        $sumY = 0;
        $count = 0;
        for ($y = 0; $y < $height; $y += self::SAMPLE_STEP) {
            for ($x = 0; $x < $width; $x += self::SAMPLE_STEP) {
                $color = imagecolorat($image, $x, $y);
                $distance = abs((($color >> 16) & 0xFF) - $backR)
                    + abs((($color >> 8) & 0xFF) - $backG)
                    + abs(($color & 0xFF) - $backB);
                if ($distance > self::BACKGROUND_DELTA) {
                    $sumX += $x;
                    $sumY += $y;
                    $count++;
                }
            }
        }

        if ($count === 0) {
            return [(int) round($width / 2), (int) round($height / 2)];
        }

        return [(int) round($sumX / $count), (int) round($sumY / $count)];
    }

    /**
     * The four corners sampled together: in a product scene they are the
     * backdrop, which is the only background definition that survives a
     * gradient studio sweep.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function averageCorners(\GdImage $image, int $width, int $height): array
    {
        $points = [[0, 0], [$width - 1, 0], [0, $height - 1], [$width - 1, $height - 1]];
        $red = 0;
        $green = 0;
        $blue = 0;
        foreach ($points as [$x, $y]) {
            $color = imagecolorat($image, $x, $y);
            $red += ($color >> 16) & 0xFF;
            $green += ($color >> 8) & 0xFF;
            $blue += $color & 0xFF;
        }

        return [(int) round($red / 4), (int) round($green / 4), (int) round($blue / 4)];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function encode(\GdImage $image, string $mime): array
    {
        ob_start();
        if ($mime === 'image/jpeg') {
            imagejpeg($image, null, self::QUALITY);
            $bytes = (string) ob_get_clean();

            return [$bytes, 'image/jpeg'];
        }
        if ($mime === 'image/webp') {
            imagewebp($image, null, self::QUALITY);
            $bytes = (string) ob_get_clean();

            return [$bytes, 'image/webp'];
        }

        imagepng($image);
        $bytes = (string) ob_get_clean();

        // Anything GD cannot write in its own format (gif, bmp, an unknown
        // mime) is delivered as png rather than as a lie.
        return [$bytes, 'image/png'];
    }
}
