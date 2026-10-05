<?php

namespace App\Domains\Credits\Controllers;

use App\Domains\Billing\Requests\TopupRequest;
use App\Domains\Billing\Services\BillingService;
use App\Domains\Credits\Services\CreditEstimator;
use App\Domains\Credits\Services\CreditService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    use ApiResponse;

    public function packs(): JsonResponse
    {
        return $this->success(array_values(config('credits.packs')));
    }

    public function topup(TopupRequest $request, BillingService $billing): JsonResponse
    {
        return $this->success(
            $billing->checkoutTopup(
                $request->user(),
                $request->string('pack')->value(),
                $request->header('Idempotency-Key'),
            ),
            201,
        );
    }

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
            'type' => ['required', 'string', 'in:image,video,text,edit'],
            'video_duration_seconds' => ['nullable', 'integer', 'in:5,8,10'],
            'quality' => ['nullable', 'string', 'in:standard,premium'],
            'operation' => ['nullable', 'string', 'required_if:type,edit', 'in:remove_bg,upscale,expand,shadow'],
            'target' => ['nullable', 'string', 'in:hd,2k,4k'],
        ], [
            'type.required' => 'انتخاب نوع خروجی الزامی است.',
            'type.in' => 'نوع خروجی باید تصویر (image)، ویدیو (video)، متن (text) یا ویرایش (edit) باشد.',
            'video_duration_seconds.in' => 'مدت زمان ویدیو باید یکی از مقادیر ۵، ۸ یا ۱۰ ثانیه باشد.',
            'quality.in' => 'کیفیت خروجی باید استاندارد یا پرمیوم باشد.',
            'operation.in' => 'عملیات ویرایش معتبر نیست.',
            'operation.required_if' => 'برای تخمین ویرایش، عملیات را انتخاب کنید.',
            'target.in' => 'وضوح مقصد باید hd، 2k یا 4k باشد.',
        ]);
        $quality = $data['quality'] ?? 'standard';
        // Ask the plan first: free users only ever pay standard pricing.
        $maxQuality = (string) config('plans.'.($request->user()->plan_key ?: 'free').'.quality', 'standard');
        $blocked = $quality === 'premium' && $maxQuality !== 'premium';

        // Utility tools are flat/step priced, independent of the plan gate.
        if ($data['type'] === 'edit') {
            $operation = (string) ($data['operation'] ?? 'remove_bg');
            $target = $data['target'] ?? null;
            // Upscaling past HD is a premium perk, mirroring premium quality.
            $planBlocked = $operation === 'upscale' && in_array($target, ['2k', '4k'], true) && $maxQuality !== 'premium';
            // Price the requested target as-is: the flag tells the UI what
            // the plan gate will do, the cost stays honest either way.
            $cost = $estimator->estimateEdit($operation, $target);
            $balance = $credits->account($request->user())->balance;

            return $this->success([
                'type' => 'edit',
                'operation' => $operation,
                'target' => $target,
                'quality' => null,
                'quality_blocked' => false,
                'plan_blocked' => $planBlocked,
                'cost' => $cost,
                'balance' => $balance,
                'sufficient' => $balance >= $cost,
                'pricing_url' => $balance >= $cost ? null : '/pricing',
            ]);
        }

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
