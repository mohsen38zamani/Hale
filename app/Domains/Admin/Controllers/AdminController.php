<?php

namespace App\Domains\Admin\Controllers;

use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Models\Generation;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use ApiResponse;

    public function users(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('creditAccount')
            ->withCount('generations');

        if ($search = $request->string('search')->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(min($request->integer('per_page', 20), 100));

        return $this->success($users);
    }

    public function user(User $user, CreditService $credits): JsonResponse
    {
        $account = $credits->account($user);
        $activeSub = $user->subscriptions()->where('status', 'active')->latest('ends_at')->first();
        $latestGenerations = $user->generations()->latest()->take(10)->get();
        $transactions = $account->transactions()->latest()->take(15)->get();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'plan_key' => $user->plan_key,
            'credits_balance' => $account->balance,
            'credits_reserved' => $account->reserved,
            'lifetime_used' => $account->lifetime_used,
            'active_subscription' => $activeSub,
            'recent_generations' => $latestGenerations,
            'recent_transactions' => $transactions,
            'created_at' => $user->created_at,
        ]);
    }

    public function generations(Request $request): JsonResponse
    {
        $query = Generation::query()
            ->with(['user:id,name,email', 'usageLogs']);

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($type = $request->string('type')->value()) {
            $query->where('type', $type);
        }

        $generations = $query->latest()->paginate(min($request->integer('per_page', 20), 100));

        return $this->success($generations);
    }

    public function refund(Request $request, User $user, CreditService $credits): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $granted = $credits->manualRefund(
            $user,
            (int) $validated['amount'],
            (string) $validated['reason'],
            $request->user()->id
        );

        return $this->success([
            'user_id' => $user->id,
            'amount_refunded' => $granted,
            'new_balance' => $credits->account($user)->balance,
        ]);
    }
}
