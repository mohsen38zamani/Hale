<?php

namespace App\Domains\Billing\Controllers;

use App\Domains\Billing\Models\Payment;
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
        return $this->success(
            $billing->checkout(
                $request->user(),
                $request->string('plan_key')->value(),
                $request->header('Idempotency-Key'),
            ),
            201,
        );
    }

    public function payments(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 15), 50));
        $payments = $request->user()->payments()
            ->latest('id')
            ->paginate($perPage);

        return $this->success($payments);
    }

    public function receipt(Request $request, Payment $payment)
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        $receipt = implode(PHP_EOL, [
            'Hale payment receipt',
            'Payment ID: '.$payment->id,
            'Plan: '.$payment->plan_key,
            'Amount (IRR): '.$payment->amount,
            'Status: '.$payment->status,
            'Reference: '.($payment->reference ?? '-'),
            'Paid at: '.($payment->paid_at?->toIso8601String() ?? '-'),
        ]).PHP_EOL;

        return response()->streamDownload(static function () use ($receipt): void {
            echo $receipt;
        }, "payment-{$payment->id}-receipt.txt", ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function invoice(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 404);

        $invoice = $payment->invoice;
        abort_if($invoice === null, 404);

        return $this->success($invoice);
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

    public function zarinpalCallback(Request $request, BillingService $billing)
    {
        $authority = $request->string('Authority')->value();
        $status = strtoupper($request->string('Status')->value()) === 'OK' ? 'paid' : 'failed';

        abort_unless($authority !== '', 422, 'شناسه پرداخت دریافت نشد.');

        $result = $billing->settle($authority, $status);

        if (! $request->wantsJson()) {
            return redirect('/pricing?payment='.$status);
        }

        return $this->success($result);
    }
}
