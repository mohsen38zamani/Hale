<?php

namespace App\Domains\Admin\Controllers;

use App\Domains\Admin\Models\SystemSetting;
use App\Domains\Billing\Models\Payment;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Models\Generation;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ], [
            'amount.required' => 'وارد کردن میزان اعتبار الزامی است.',
            'amount.integer' => 'میزان اعتبار باید عدد صحیح باشد.',
            'amount.min' => 'میزان اعتبار باید حداقل ۱ باشد.',
            'reason.required' => 'ثبت دلیل بازگشت اعتبار الزامی است.',
            'reason.max' => 'دلیل بازگشت اعتبار نمی‌تواند بیش از ۲۵۵ کاراکتر باشد.',
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

    public function metrics(): JsonResponse
    {
        $totalUsers = User::count();

        // 1. Activation & Second Generation (PRD: >60% activation, >30% second gen)
        $usersWithCompletedGens = DB::table('generations')
            ->where('status', 'completed')
            ->select('user_id', DB::raw('count(*) as aggregate_count'))
            ->groupBy('user_id')
            ->get();

        $activatedUsers = $usersWithCompletedGens->count();
        $activationRate = $totalUsers > 0 ? round(($activatedUsers / $totalUsers) * 100, 2) : 0.0;

        $secondGenUsers = $usersWithCompletedGens->where('aggregate_count', '>=', 2)->count();
        $secondGenRate = $activatedUsers > 0 ? round(($secondGenUsers / $activatedUsers) * 100, 2) : 0.0;

        // 2. Feedback Satisfaction Rate (PRD: 👍 Rate > 50%)
        $thumbsUp = Generation::whereIn('feedback', ['positive', 'thumbs_up'])->count();
        $thumbsDown = Generation::whereIn('feedback', ['negative', 'thumbs_down'])->count();
        $totalFeedback = $thumbsUp + $thumbsDown;
        $thumbsUpRate = $totalFeedback > 0 ? round(($thumbsUp / $totalFeedback) * 100, 2) : 0.0;

        // 3. Free to Paid Conversion (PRD: > 5%)
        $paidUsers = Payment::where('status', 'paid')->distinct('user_id')->count('user_id');
        $freeToPaidRate = $totalUsers > 0 ? round(($paidUsers / $totalUsers) * 100, 2) : 0.0;

        // 4. Financials & Gross Margin (PRD: Gross Margin > 50%)
        $totalRevenueToman = (int) Payment::where('status', 'paid')->sum('amount');
        $totalCostUsd = (float) Generation::where('status', 'completed')->sum('cost_usd');

        $usdToTomanRate = (int) SystemSetting::get('usd_to_toman_rate', (int) config('payment.usd_to_toman_rate', 100000));
        $totalCostToman = (int) round($totalCostUsd * $usdToTomanRate);
        $grossProfitToman = $totalRevenueToman - $totalCostToman;
        $grossMarginRate = $totalRevenueToman > 0
            ? round(($grossProfitToman / $totalRevenueToman) * 100, 2)
            : 0.0;

        return $this->success([
            'overview' => [
                'total_users' => $totalUsers,
                'total_generations' => Generation::count(),
                'completed_generations' => Generation::where('status', 'completed')->count(),
            ],
            'kpis' => [
                'activation' => [
                    'activated_users' => $activatedUsers,
                    'rate_percentage' => $activationRate,
                    'target' => '> 60%',
                    'status' => $activationRate >= 60 ? 'pass' : 'needs_attention',
                ],
                'second_generation' => [
                    'second_gen_users' => $secondGenUsers,
                    'rate_percentage' => $secondGenRate,
                    'target' => '> 30%',
                    'status' => $secondGenRate >= 30 ? 'pass' : 'needs_attention',
                ],
                'thumbs_up_rate' => [
                    'thumbs_up' => $thumbsUp,
                    'thumbs_down' => $thumbsDown,
                    'total_feedback' => $totalFeedback,
                    'rate_percentage' => $thumbsUpRate,
                    'target' => '> 50%',
                    'status' => $thumbsUpRate >= 50 ? 'pass' : 'needs_attention',
                ],
                'free_to_paid' => [
                    'paid_users' => $paidUsers,
                    'rate_percentage' => $freeToPaidRate,
                    'target' => '> 5%',
                    'status' => $freeToPaidRate >= 5 ? 'pass' : 'needs_attention',
                ],
                'gross_margin' => [
                    'revenue_toman' => $totalRevenueToman,
                    'cost_usd' => $totalCostUsd,
                    'cost_toman_estimate' => $totalCostToman,
                    'profit_toman_estimate' => $grossProfitToman,
                    'margin_percentage' => $grossMarginRate,
                    'target' => '> 50%',
                    'status' => $grossMarginRate >= 50 ? 'pass' : 'needs_attention',
                ],
            ],
        ]);
    }

    public function settings(): JsonResponse
    {
        return $this->success(SystemSetting::query()->get());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['sometimes', 'string', 'max:100'],
            'value' => ['sometimes', 'nullable', 'max:10000'],
            'settings' => ['sometimes', 'array', 'max:50'],
            'settings.*' => ['nullable', 'max:10000'],
        ]);

        if (isset($data['key']) && array_key_exists('value', $data)) {
            SystemSetting::set($data['key'], $data['value']);
        }

        if (isset($data['settings']) && is_array($data['settings'])) {
            foreach ($data['settings'] as $key => $value) {
                SystemSetting::set($key, $value);
            }
        }

        return $this->success([
            'message' => 'تنظیمات سیستم با موفقیت به‌روزرسانی شد.',
            'settings' => SystemSetting::query()->get(),
        ]);
    }
}
