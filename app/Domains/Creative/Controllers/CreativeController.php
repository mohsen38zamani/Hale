<?php

namespace App\Domains\Creative\Controllers;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Creative\Enums\FidelityMode;
use App\Domains\Creative\Requests\CompilePromptRequest;
use App\Domains\Creative\Requests\PreviewCreativeRequest;
use App\Domains\Creative\Services\CreativeEngine;
use App\Domains\Creative\Services\SeasonThemeService;
use App\Domains\Credits\Exceptions\InsufficientCredits;
use App\Domains\Credits\Services\CreditEstimator;
use App\Domains\Credits\Services\CreditService;
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
            // The ten trades with their protection rules, scale locks and
            // one-click packages, in catalogue order, plus the two fidelity
            // pipelines the packages choose between.
            'categories' => array_values(config('creative_categories.categories')),
            'fidelity_modes' => array_map(fn (FidelityMode $mode) => $mode->value, FidelityMode::cases()),
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

        $settings = $engine->autoBest($product, $goal);

        // Whatever the studio already chose wins over the suggestion: the
        // inspector has to quote the prompt the user is looking at, not the
        // one autoBest would have proposed in its place. Blank inputs keep
        // the suggested value, which is why filled() decides.
        foreach (['style', 'format', 'environment', 'category', 'fidelity_mode', 'video_duration_seconds', 'custom_prompt', 'surface', 'props', 'camera_angle', 'lighting_setup', 'character_consistency', 'target_ai'] as $key) {
            if ($request->filled($key)) {
                $settings[$key] = $request->input($key);
            }
        }
        if ($request->boolean('campaign')) {
            $settings['campaign'] = true;
        }
        if ($request->filled('season_theme')) {
            $settings['season_theme'] = $request->string('season_theme')->toString();
        }

        $format = CreativeFormat::from($settings['format']);
        $brief = $engine->brief($product, $settings, $request->user()->brandKit);
        $prompt = $engine->prompt($brief, $format);
        $creditCost = $estimator->estimate($format->type(), $settings['video_duration_seconds'] ?? null);
        $hasTrade = ! empty($settings['category']) && $settings['category'] !== 'general';
        $promptCost = $estimator->estimatePrompt($format->type(), $hasTrade);

        return $this->success([
            ...$settings,
            'type' => $format->type(),
            'brief' => $brief,
            'prompt_preview' => $prompt,
            'estimated_credits' => $creditCost,
            'estimated_prompt_credits' => $promptCost,
        ]);
    }

    /**
     * Official commercial endpoint to purchase and compile a self-contained prompt (Track 2, spec item 13).
     *
     * Charges 1, 2, or 5 credits atomically, records the prompt_compilation
     * ledger entry, and returns the compiled prompt tailored to the target model.
     */
    public function compilePrompt(
        CompilePromptRequest $request,
        CreativeEngine $engine,
        CreditService $credits,
        CreditEstimator $estimator
    ): JsonResponse {
        $user = $request->user();
        $product = Product::query()->findOrFail($request->integer('product_id'));
        abort_unless($product->user_id === $user->id, 404);
        $goal = CreativeGoal::from($request->input('goal', CreativeGoal::Introduction->value));

        $settings = $engine->autoBest($product, $goal);

        foreach (['style', 'format', 'environment', 'category', 'fidelity_mode', 'video_duration_seconds', 'custom_prompt', 'surface', 'props', 'camera_angle', 'lighting_setup', 'character_consistency', 'target_ai'] as $key) {
            if ($request->filled($key)) {
                $settings[$key] = $request->input($key);
            }
        }
        if ($request->boolean('campaign')) {
            $settings['campaign'] = true;
        }
        if ($request->filled('season_theme')) {
            $settings['season_theme'] = $request->string('season_theme')->toString();
        }

        $format = CreativeFormat::from($settings['format']);
        $hasTrade = ! empty($settings['category']) && $settings['category'] !== 'general';
        $cost = $estimator->estimatePrompt($format->type(), $hasTrade);

        $idempotencyKey = $request->header('Idempotency-Key')
            ?? $request->string('idempotency_key')->toString()
            ?: 'compile_prompt:'.$user->id.':'.md5(json_encode([$product->id, $settings]));

        try {
            $newBalance = $credits->spendForPrompt($user, $cost, $idempotencyKey, [
                'product_id' => $product->id,
                'format' => $format->value,
                'type' => $format->type(),
                'target_ai' => $settings['target_ai'] ?? 'generic',
                'category' => $settings['category'] ?? null,
                'has_trade' => $hasTrade,
            ]);
        } catch (InsufficientCredits $exception) {
            return $this->failure('INSUFFICIENT_CREDITS', $exception->getMessage(), 402);
        }

        $brief = $engine->brief($product, $settings, $user->brandKit);
        $prompt = $engine->prompt($brief, $format);
        $targetAiKey = (string) ($settings['target_ai'] ?? 'generic');
        /** @var string $targetAiName */
        $targetAiName = config("creative.target_ais.{$targetAiKey}.name", 'عمومی');

        return $this->success([
            'prompt' => $prompt,
            'target_ai' => $targetAiKey,
            'target_ai_name' => $targetAiName,
            'credits_charged' => $cost,
            'new_balance' => $newBalance,
            'brief' => $brief,
            'settings' => $settings,
            'type' => $format->type(),
        ]);
    }
}
