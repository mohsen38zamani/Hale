<?php

namespace Tests\Unit\Credits;

use App\Domains\Credits\Services\CreditEstimator;
use Tests\TestCase;

class CreditEstimatorTest extends TestCase
{
    public function test_it_estimates_image_and_duration_based_video_costs(): void
    {
        $estimator = new CreditEstimator;

        $this->assertSame(10, $estimator->estimate('image'));
        $this->assertSame(60, $estimator->estimate('video', 8));
        $this->assertSame(45, $estimator->estimate('video')); // default 5 seconds
        $this->assertSame(45, $estimator->estimate('video', 2)); // clamped to min 5 seconds
    }

    public function test_it_estimates_text_tasks_from_config(): void
    {
        $estimator = new CreditEstimator;

        // Must never fall through to the duration-based video formula.
        $this->assertSame((int) config('credits.costs.text'), $estimator->estimate('text'));
        $this->assertSame(3, $estimator->estimate('text'));
    }

    public function test_it_estimates_utility_tool_operations(): void
    {
        $estimator = new CreditEstimator;

        $this->assertSame(5, $estimator->estimateEdit('remove_bg'));
        $this->assertSame(5, $estimator->estimateEdit('shadow'));
        $this->assertSame(10, $estimator->estimateEdit('expand'));
        $this->assertSame(5, $estimator->estimateEdit('upscale'));
        $this->assertSame(5, $estimator->estimateEdit('upscale', 'hd'));
        $this->assertSame(10, $estimator->estimateEdit('upscale', '2k'));
        $this->assertSame(15, $estimator->estimateEdit('upscale', '4k'));
        // Unknown operations degrade to the cheapest tier instead of 0.
        $this->assertSame(5, $estimator->estimateEdit('unknown_tool'));
    }
}
