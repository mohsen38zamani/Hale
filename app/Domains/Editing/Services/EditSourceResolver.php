<?php

namespace App\Domains\Editing\Services;

use App\Domains\Editing\Exceptions\SourceNotFoundException;
use App\Domains\Editing\Exceptions\SourceNotProcessableException;
use App\Domains\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Maps an edit's (source_type, source_id) pair to the actual source photo.
 *
 * Ownership is enforced by querying through the user's own relations, so a
 * foreign id is indistinguishable from a missing one (both 404).
 */
class EditSourceResolver
{
    /**
     * @return array{disk: string, path: string, mime: string}
     *
     * @throws SourceNotFoundException when the source does not exist (or is not the user's)
     * @throws SourceNotProcessableException when the source exists but has no usable image
     */
    public function resolve(string $sourceType, int $sourceId, User $user): array
    {
        $asset = match ($sourceType) {
            'generation' => $this->generationAsset($sourceId, $user),
            'product' => $this->productAsset($sourceId, $user),
            default => throw new SourceNotFoundException('نوع منبع ویرایش معتبر نیست.'),
        };

        if ($asset === null) {
            throw new SourceNotFoundException('منبع ویرایش یافت نشد.');
        }

        if (! Storage::disk($asset->disk)->exists($asset->path)) {
            throw new SourceNotProcessableException('فایل منبع در دسترس نیست.');
        }

        if (! str_starts_with((string) $asset->mime, 'image/')) {
            throw new SourceNotProcessableException('فقط تصویر قابل ویرایش است.');
        }

        return ['disk' => (string) $asset->disk, 'path' => (string) $asset->path, 'mime' => (string) $asset->mime];
    }

    private function generationAsset(int $sourceId, User $user): ?MediaAsset
    {
        $generation = $user->generations()->find($sourceId);
        if ($generation === null) {
            return null;
        }

        if ($generation->type !== 'image' || $generation->status !== 'completed' || $generation->output_media_id === null) {
            throw new SourceNotProcessableException('این خروجی هنوز تصویر تکمیل‌شده‌ای ندارد.');
        }

        return $generation->outputMedia;
    }

    private function productAsset(int $sourceId, User $user): ?MediaAsset
    {
        $product = $user->products()->with('assets')->find($sourceId);
        if ($product === null) {
            return null;
        }

        $asset = $product->assets->first(fn (MediaAsset $asset): bool => (bool) $asset->pivot->is_primary)
            ?? $product->assets->first();

        if ($asset === null) {
            throw new SourceNotProcessableException('این محصول تصویری ندارد.');
        }

        return $asset;
    }
}
