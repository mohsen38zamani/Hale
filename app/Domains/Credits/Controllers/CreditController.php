<?php

namespace App\Domains\Credits\Controllers;

use App\Domains\Credits\Services\CreditEstimator;
use App\Domains\Credits\Services\CreditService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    use ApiResponse;

    public function balance(Request $request, CreditService $credits): JsonResponse
    {
        return $this->success($credits->account($request->user()));
    }

    public function transactions(Request $request, CreditService $credits): JsonResponse
    {
        return $this->success($credits->account($request->user())->transactions()->latest()->paginate(20));
    }

    public function estimate(Request $request, CreditEstimator $estimator, CreditService $credits): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:image,video'],
            'video_duration_seconds' => ['nullable', 'integer', 'in:5,8,10'],
            'quality' => ['nullable', 'string', 'in:standard,premium'],
        ], [
            'type.required' => 'انتخاب نوع خروجی الزامی است.',
            'type.in' => 'نوع خروجی باید تصویر (image) یا ویدیو (video) باشد.',
            'video_duration_seconds.in' => 'مدت زمان ویدیو باید یکی از مقادیر ۵، ۸ یا ۱۰ ثانیه باشد.',
            'quality.in' => 'کیفیت خروجی باید استاندارد یا پرمیوم باشد.',
        ]);
        $quality = $data['quality'] ?? 'standard';
        // Ask the plan first: free users only ever pay standard pricing.
        $maxQuality = (string) config('plans.'.($request->user()->plan_key ?: 'free').'.quality', 'standard');
        $blocked = $quality === 'premium' && $maxQuality !== 'premium';
        $cost = $estimator->estimate($data['type'], $data['video_duration_seconds'] ?? null, $blocked ? 'standard' : $quality);
        $balance = $credits->account($request->user())->balance;

        return $this->success([
            'type' => $data['type'],
            'quality' => $blocked ? 'standard' : $quality,
            'quality_blocked' => $blocked,
            'cost' => $cost,
            'balance' => $balance,
            'sufficient' => $balance >= $cost,
            'pricing_url' => $balance >= $cost ? null : '/pricing',
        ]);
    }
}
