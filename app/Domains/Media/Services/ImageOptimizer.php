<?php

namespace App\Domains\Media\Services;

use App\Domains\Media\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImageOptimizer
{
    /**
     * Decode image bytes and re-encode them as a screen-sized WebP.
     *
     * Returns null when the runtime cannot process images, when the source
     * is not a decodable image (video outputs, corrupt files) or when the
     * source is already an in-limit WebP and re-encoding would only lose
     * quality without saving bytes.
     *
     * @return array{contents: string, mime: string, extension: string}|null
     */
    public function optimize(string $contents, ?string $sourceMime = null): ?array
    {
        if ($contents === '' || ! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);

            return null;
        }

        $limit = max(16, (int) config('media.web_max_dimension', 1600));
        if ($sourceMime === 'image/webp' && $width <= $limit && $height <= $limit) {
            imagedestroy($image);

            return null;
        }

        $scale = min($limit / $width, $limit / $height, 1);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        if ($scale < 1) {
            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        $written = imagewebp($image, null, max(1, min(100, (int) config('media.web_quality', 82))));
        $encoded = ob_get_clean();
        imagedestroy($image);

        if ($written !== true || ! is_string($encoded) || $encoded === '') {
            return null;
        }

        return ['contents' => $encoded, 'mime' => 'image/webp', 'extension' => 'webp'];
    }

    /**
     * Create (or reuse) the deterministic web variant of a stored asset.
     * Returns the stored path, or null when no variant can be produced.
     */
    public function webVariantFor(MediaAsset $asset): ?string
    {
        $disk = Storage::disk($asset->disk);

        if ($asset->web_path !== null && $disk->exists($asset->web_path)) {
            return $asset->web_path;
        }

        try {
            $contents = $disk->get($asset->path);
        } catch (Throwable) {
            return null;
        }

        $optimized = $this->optimize($contents, $asset->mime);
        if ($optimized === null) {
            return null;
        }

        // Deterministic path: concurrent first requests converge on the same
        // file instead of leaving orphan variants behind.
        $path = 'web/'.$asset->getKey().'.webp';
        if (! $disk->put($path, $optimized['contents'])) {
            return null;
        }

        if ($asset->web_path !== $path) {
            $asset->update(['web_path' => $path]);
        }

        return $path;
    }
}
