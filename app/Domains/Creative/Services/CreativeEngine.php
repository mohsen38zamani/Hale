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
        $name = mb_strtolower($product->name.' '.($product->description ?? ''));

        $style = match ($goal) {
            CreativeGoal::Sales, CreativeGoal::Promotion => CreativeStyle::Colorful,
            CreativeGoal::Branding => CreativeStyle::Luxury,
            CreativeGoal::Engagement => CreativeStyle::Cinematic,
            CreativeGoal::Launch => CreativeStyle::Fashion,
            default => str_contains($name, 'عطر') || str_contains($name, 'perfume') || str_contains($name, 'طلا') || str_contains($name, 'jewelry')
                ? CreativeStyle::Luxury
                : CreativeStyle::Professional,
        };

        $environment = match (true) {
            str_contains($name, 'عطر') || str_contains($name, 'perfume') => 'luxury',
            str_contains($name, 'گیاه') || str_contains($name, 'طبیعی') || str_contains($name, 'natural') => 'nature',
            str_contains($name, 'لباس') || str_contains($name, 'پوشاک') || str_contains($name, 'dress') => 'street',
            str_contains($name, 'کافه') || str_contains($name, 'غذا') || str_contains($name, 'food') => 'cafe',
            default => 'studio',
        };

        $format = match ($goal) {
            CreativeGoal::Engagement, CreativeGoal::Launch => CreativeFormat::InstagramReel,
            default => CreativeFormat::InstagramPost,
        };

        $duration = $format->type() === 'video' ? 5 : null;

        return [
            'goal' => $goal->value,
            'style' => $style->value,
            'format' => $format->value,
            'environment' => $environment,
            'aspect_ratio' => $format->aspectRatio(),
            'video_duration_seconds' => $duration,
        ];
    }

    public function brief(Product $product, array $settings): array
    {
        return [
            'product' => $product->name,
            'description' => $product->description,
            'objective' => $settings['goal'],
            'visual_direction' => $settings['style'],
            'environment' => $settings['environment'] ?? 'studio',
            'format' => $settings['format'],
            'audience' => 'Iranian social commerce shoppers',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function prompt(array $brief, CreativeFormat $format): string
    {
        $base = sprintf(
            'Create a professional commercial advertising visual for %s. Objective: %s. Aesthetic style: %s. Environment: %s. Composition: %s ratio (%s). High-end commercial production, photorealistic, cinematic lighting, ultra-sharp detail, preserve original product design and packaging, no distracting watermarks, no unwanted text.',
            $brief['product'],
            $brief['objective'],
            $brief['visual_direction'],
            $brief['environment'],
            $format->aspectRatio(),
            $format->value
        );

        if ($format->type() === 'video') {
            $base .= ' Dynamic motion: smooth cinematic camera pan, fluid atmospheric movement, premium brand reel aesthetic, 4K render.';
        }

        return $base;
    }
}
