<?php

declare(strict_types=1);

namespace App\Domains\Generations\Jobs;

use App\Domains\Generations\Services\ProductImageAnalyzer;
use App\Domains\Media\Models\MediaAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * لایهٔ ۲ تحلیل دورگهٔ محصول: فراخوانی مدل بینایی در صف (غیرمسدودکننده).
 */
class AnalyzeProductTrade implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** یک تحلیل همزمان برای هر تصویر کافی است. */
    public int $uniqueFor = 600;

    public function __construct(public int $assetId) {}

    public function uniqueId(): string
    {
        return 'product-trade-analysis-'.$this->assetId;
    }

    public function handle(ProductImageAnalyzer $analyzer): void
    {
        $asset = MediaAsset::find($this->assetId);

        if (! $asset) {
            return;
        }

        $analyzer->analyzeWithVision($asset);
    }
}
