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
}
