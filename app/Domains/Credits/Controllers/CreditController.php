<?php

namespace App\Domains\Credits\Controllers;

use App\Domains\Credits\Services\CreditService;
use App\Domains\Credits\Services\CreditEstimator;
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
        ]);
        $cost = $estimator->estimate($data['type'], $data['video_duration_seconds'] ?? null);
        $balance = $credits->account($request->user())->balance;

        return $this->success([
            'type' => $data['type'],
            'cost' => $cost,
            'balance' => $balance,
            'sufficient' => $balance >= $cost,
            'pricing_url' => $balance >= $cost ? null : '/pricing',
        ]);
    }
}
