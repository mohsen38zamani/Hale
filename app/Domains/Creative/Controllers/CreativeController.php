<?php

namespace App\Domains\Creative\Controllers;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Creative\Requests\PreviewCreativeRequest;
use App\Domains\Creative\Services\CreativeEngine;
use App\Domains\Creative\Services\SeasonThemeService;
use App\Domains\Credits\Services\CreditEstimator;
use App\Domains\Products\Models\Product;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class CreativeController extends Controller
{
    use ApiResponse;

    public function options(): JsonResponse
    {
        return $this->success([
            'goals' => array_column(CreativeGoal::cases(), 'value'),
            'styles' => array_column(CreativeStyle::cases(), 'value'),
            'formats' => array_map(fn (CreativeFormat $format) => ['key' => $format->value, 'type' => $format->type(), 'aspect_ratio' => $format->aspectRatio()], CreativeFormat::cases()),
            'environments' => config('creative.environments'),
            'video_durations' => config('creative.video_durations'),
            'surfaces' => array_values(config('creative.surfaces')),
            'props' => array_values(config('creative.props')),
            'camera_angles' => array_values(config('creative.camera_angles')),
            'lighting_setups' => array_values(config('creative.lighting_setups')),
            'character_consistencies' => array_values(config('creative.character_consistencies')),
            'impact_levels' => array_values(config('creative.impact_levels')),
            'impacts' => config('creative.impacts'),
        ]);
    }

    public function theme(SeasonThemeService $seasons): JsonResponse
    {
        return $this->success([
            'theme' => $seasons->active(),
            // Full catalogue so the studio picker can list, preview and pin
            // every seasonal theme instead of only the resolved one.
            'themes' => config('seasons.themes'),
            // Deployment kill switch: SEASON_THEME=off hides the picker and
            // wins over any client-side selection.
            'enabled' => ! in_array((string) config('seasons.active'), ['off', 'none'], true),
        ]);
    }

    public function preview(PreviewCreativeRequest $request, CreativeEngine $engine, CreditEstimator $estimator): JsonResponse
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));
        abort_unless($product->user_id === $request->user()->id, 404);
        $goal = CreativeGoal::from($request->input('goal', CreativeGoal::Introduction->value));

        $suggestion = $engine->autoBest($product, $goal);
        $format = CreativeFormat::from($suggestion['format']);
        $settings = $suggestion;
        if ($request->boolean('campaign')) {
            $settings['campaign'] = true;
        }
        if ($request->filled('season_theme')) {
            $settings['season_theme'] = $request->string('season_theme')->toString();
        }
        if ($request->filled('character_consistency')) {
            $settings['character_consistency'] = $request->string('character_consistency')->toString();
        }
        $brief = $engine->brief($product, $settings, $request->user()->brandKit);
        $prompt = $engine->prompt($brief, $format);
        $creditCost = $estimator->estimate($format->type(), $suggestion['video_duration_seconds'] ?? null);

        return $this->success([
            ...$suggestion,
            'type' => $format->type(),
            'brief' => $brief,
            'prompt_preview' => $prompt,
            'estimated_credits' => $creditCost,
        ]);
    }
}
