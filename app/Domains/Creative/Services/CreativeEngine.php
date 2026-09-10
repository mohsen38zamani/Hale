<?php

namespace App\Domains\Creative\Services;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Products\Models\Product;

class CreativeEngine
{
    public function autoBest(Product $product, CreativeGoal $goal): array
    {
        $style = match ($goal) {
            CreativeGoal::Sales, CreativeGoal::Promotion => CreativeStyle::Colorful,
            CreativeGoal::Branding => CreativeStyle::Luxury,
            CreativeGoal::Engagement => CreativeStyle::Cinematic,
            default => CreativeStyle::Professional,
        };

        return ['goal' => $goal->value, 'style' => $style->value, 'format' => CreativeFormat::InstagramPost->value, 'environment' => 'studio', 'aspect_ratio' => '1:1'];
    }

    public function brief(Product $product, array $settings): array
    {
        return ['product' => $product->name, 'objective' => $settings['goal'], 'visual_direction' => $settings['style'], 'environment' => $settings['environment'] ?? 'studio', 'format' => $settings['format'], 'audience' => 'Iranian social commerce shoppers'];
    }

    public function prompt(array $brief, CreativeFormat $format): string
    {
        return sprintf('Create a professional advertising visual for %s. Objective: %s. Style: %s. Environment: %s. Format: %s (%s). Preserve product identity, realistic lighting, no text, no logos, commercial photography.', $brief['product'], $brief['objective'], $brief['visual_direction'], $brief['environment'], $format->value, $format->aspectRatio());
    }
}
