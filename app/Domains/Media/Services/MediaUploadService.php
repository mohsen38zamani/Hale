<?php

namespace App\Domains\Media\Services;

use App\Domains\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaUploadService
{
    public function storeProductImage(User $user, UploadedFile $file): MediaAsset
    {
        $disk = config('filesystems.media_disk');
        $directory = "users/{$user->id}/products";
        $filename = Str::uuid().'.'.$file->extension();
        $path = $file->storeAs($directory, $filename, $disk);
        [$width, $height] = getimagesize($file->getRealPath()) ?: [null, null];
        $thumbnailPath = $this->createThumbnail($file, $directory, $disk);

        return $user->mediaAssets()->create([
            'disk' => $disk,
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function delete(MediaAsset $asset): void
    {
        Storage::disk($asset->disk)->delete(array_filter([$asset->path, $asset->thumbnail_path]));
        $asset->delete();
    }

    private function createThumbnail(UploadedFile $file, string $directory, string $disk): string
    {
        $sourceContents = file_get_contents($file->getRealPath());
        $source = $sourceContents === false ? false : imagecreatefromstring($sourceContents);

        if ($source === false) {
            throw new RuntimeException('Unable to process the uploaded image.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(400 / $sourceWidth, 400 / $sourceHeight, 1);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $thumbnail = imagecreatetruecolor($width, $height);
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        imagewebp($thumbnail, null, 82);
        $contents = ob_get_clean();
        imagedestroy($source);
        imagedestroy($thumbnail);

        if ($contents === false) {
            throw new RuntimeException('Unable to generate an image thumbnail.');
        }

        $path = $directory.'/thumbnails/'.Str::uuid().'.webp';
        Storage::disk($disk)->put($path, $contents);

        return $path;
    }
}
