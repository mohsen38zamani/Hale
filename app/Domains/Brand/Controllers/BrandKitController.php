<?php

namespace App\Domains\Brand\Controllers;

use App\Domains\Brand\Models\BrandKit;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandKitController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        return $this->success($request->user()->brandKit);
    }

    public function update(Request $request): JsonResponse
    {
        $hex = ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'primary_color' => $hex,
            'secondary_color' => $hex,
            'accent_color' => $hex,
            'font_family' => ['nullable', 'string', 'max:40'],
            'tone' => ['nullable', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:160'],
        ], [
            'name.sometimes' => 'نام برند باید متن باشد.',
            'name.max' => 'نام برند حداکثر ۶۰ کاراکتر است.',
            'primary_color.regex' => 'رنگ اصلی باید به فرمت #RRGGBB باشد.',
            'secondary_color.regex' => 'رنگ ثانویه باید به فرمت #RRGGBB باشد.',
            'accent_color.regex' => 'رنگ تأکیدی باید به فرمت #RRGGBB باشد.',
            'font_family.max' => 'نام فونت حداکثر ۴۰ کاراکتر است.',
            'tone.max' => 'لحن برند حداکثر ۶۰ کاراکتر است.',
            'tagline.max' => 'شعار برند حداکثر ۱۶۰ کاراکتر است.',
        ]);

        // Upsert: every user owns exactly one kit; PUT is idempotent.
        $kit = BrandKit::query()->updateOrCreate(['user_id' => $request->user()->getKey()], $data);

        return $this->success($kit);
    }
}
