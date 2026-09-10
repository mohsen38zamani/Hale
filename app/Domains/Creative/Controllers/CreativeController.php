<?php

namespace App\Domains\Creative\Controllers;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Creative\Requests\PreviewCreativeRequest;
use App\Domains\Creative\Services\CreativeEngine;
use App\Domains\Products\Models\Product;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class CreativeController extends Controller
{
    use ApiResponse;

    public function options(): JsonResponse
    {
        return $this->success(['goals' => array_column(CreativeGoal::cases(), 'value'), 'styles' => array_column(CreativeStyle::cases(), 'value'), 'formats' => array_map(fn (CreativeFormat $format) => ['key' => $format->value, 'type' => $format->type(), 'aspect_ratio' => $format->aspectRatio()], CreativeFormat::cases()), 'environments' => config('creative.environments'), 'video_durations' => config('creative.video_durations')]);
    }

    public function preview(PreviewCreativeRequest $request, CreativeEngine $engine): JsonResponse
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));
        abort_unless($product->user_id === $request->user()->id, 404);
        $goal = CreativeGoal::from($request->input('goal', CreativeGoal::Introduction->value));

        return $this->success($engine->autoBest($product, $goal));
    }
}
