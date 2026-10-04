<?php

return [
    // quality = highest output quality tier the plan may request:
    // standard (1K, 10 credits) or premium (2K, 25 credits).
    'free' => ['key' => 'free', 'name' => 'Free', 'monthly_credits' => 30, 'image_limit' => 30, 'video_limit' => 0, 'watermark' => true, 'price_irr' => 0, 'quality' => 'standard'],
    'starter' => ['key' => 'starter', 'name' => 'Starter', 'monthly_credits' => 200, 'image_limit' => 200, 'video_limit' => 2, 'watermark' => false, 'price_irr' => 4_990_000, 'quality' => 'premium'],
    'creator' => ['key' => 'creator', 'name' => 'Creator', 'monthly_credits' => 500, 'image_limit' => 500, 'video_limit' => 8, 'watermark' => false, 'price_irr' => 12_990_000, 'quality' => 'premium'],
];
