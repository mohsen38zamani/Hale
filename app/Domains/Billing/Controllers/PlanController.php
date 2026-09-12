<?php

namespace App\Domains\Billing\Controllers;

use App\Domains\Billing\Requests\CheckoutRequest;
use App\Domains\Billing\Services\BillingService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(array_values(config('plans')));
    }

    public function checkout(CheckoutRequest $request, BillingService $billing): JsonResponse
    {
        return $this->success($billing->checkout($request->user(), $request->string('plan_key')->value()), 201);
    }

    public function webhook(Request $request, BillingService $billing): JsonResponse
    {
        $authority = $request->string('authority')->value();
        $status = $request->string('status')->value();
        $signature = $request->header('X-Payment-Signature', '');
        $expected = hash_hmac('sha256', $authority.'|'.$status, (string) config('payment.webhook_secret'));
        abort_unless($signature !== '' && hash_equals($expected, $signature), 401, 'امضای وبهوک نامعتبر است.');

        return $this->success($billing->settle($authority, $status));
    }

    public function zarinpalCallback(Request $request, BillingService $billing): JsonResponse
    {
        $authority = $request->string('Authority')->value();
        $status = strtoupper($request->string('Status')->value()) === 'OK' ? 'paid' : 'failed';

        abort_unless($authority !== '', 422, 'شناسه پرداخت دریافت نشد.');

        return $this->success($billing->settle($authority, $status));
    }
}
