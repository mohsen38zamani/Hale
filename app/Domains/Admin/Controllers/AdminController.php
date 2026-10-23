<?php

namespace App\Domains\Admin\Controllers;

use App\Domains\Admin\Models\SystemSetting;
use App\Domains\Billing\Models\Payment;
use App\Domains\Credits\Models\CreditTransaction;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Exceptions\GenerationNotCancellable;
use App\Domains\Generations\Models\Generation;
use App\Domains\Generations\Services\CancelGeneration;
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
            'is_banned' => $user->currentlyBanned(),
            'banned_at' => $user->banned_at,
            'banned_until' => $user->banned_until,
            'ban_reason' => $user->ban_reason,
            'active_subscription' => $activeSub,
            'recent_generations' => $latestGenerations,
            'recent_transactions' => $transactions,
            'created_at' => $user->created_at,
        ]);
    }

    public function ban(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ], [
            'reason.required' => 'ثبت دلیل مسدودسازی الزامی است.',
            'reason.max' => 'دلیل مسدودسازی نمی‌تواند بیش از ۲۵۵ کاراکتر باشد.',
            'expires_at.date' => 'تاریخ پایان مسدودسازی نامعتبر است.',
            'expires_at.after' => 'تاریخ پایان مسدودسازی باید در آینده باشد.',
        ]);

        $adminEmails = array_filter(array_map('trim', explode(',', (string) config('auth.admin_emails', ''))));
        if (in_array($user->email, $adminEmails, true)) {
            return $this->error('ADMIN_ACCOUNT_PROTECTED', 'امکان مسدودسازی حساب مدیر وجود ندارد.', 422);
        }

        if ($user->currentlyBanned()) {
            return $this->error('ALREADY_BANNED', 'این حساب هم‌اکنون مسدود است.', 422);
        }

        $user->ban($validated['expires_at'] ?? null, $validated['reason']);

        return $this->success([
            'id' => $user->id,
            'is_banned' => true,
            'banned_at' => $user->fresh()->banned_at,
            'banned_until' => $user->banned_until,
            'ban_reason' => $user->ban_reason,
            'message' => 'حساب کاربری مسدود شد و نشست‌های او لغو گردید.',
        ]);
    }

    public function unban(User $user): JsonResponse
    {
        if ($user->banned_at === null) {
            return $this->error('NOT_BANNED', 'این حساب مسدود نیست.', 422);
        }

        $user->unban();

        return $this->success([
            'id' => $user->id,
            'is_banned' => false,
            'message' => 'مسدودسازی حساب کاربری برداشته شد.',
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

    public function cancelGeneration(Request $request, Generation $generation, CancelGeneration $cancel): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ], [
            'reason.max' => 'دلیل لغو نمی‌تواند بیش از ۲۵۵ کاراکتر باشد.',
        ]);

        try {
            $cancelled = $cancel->execute($generation, $validated['reason'] ?? 'توسط مدیر لغو شد.');
        } catch (GenerationNotCancellable) {
            return $this->failure('GENERATION_NOT_CANCELLABLE', 'فقط تولیدهای در صف یا در حال پردازش قابل لغو هستند.', 409);
        }

        return $this->success($cancelled);
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

        // 4. Financials & Unit Economics Ledger (spec item 13, phase 3)
        $totalRevenueToman = (int) Payment::where('status', 'paid')->sum('amount');
        $totalCostUsd = (float) Generation::where('status', 'completed')->sum('cost_usd');

        // Track 1: In-App Generation
        $genCompletedCount = Generation::where('status', 'completed')->count();
        $genCreditsSpent = (int) abs(
            CreditTransaction::query()
                ->where('delivery_path', 'in_app_generation')
                ->where('amount', '<', 0)
                ->sum('amount')
        );

        // Track 2: Prompt-Only Track
        $promptCount = CreditTransaction::query()
            ->where('type', 'prompt_compilation')
            ->count();
        $promptCreditsSpent = (int) abs(
            CreditTransaction::query()
                ->where('type', 'prompt_compilation')
                ->where('amount', '<', 0)
                ->sum('amount')
        );
        $promptUnitCostUsd = (float) SystemSetting::get('prompt_unit_cost_usd', config('credits.costs.prompt_unit_cost_usd', 0.0006));
        $promptCostUsd = round($promptCount * $promptUnitCostUsd, 4);

        // Split attributed revenue based on relative consumption of credits
        $totalTrackCredits = $genCreditsSpent + $promptCreditsSpent;
        if ($totalTrackCredits > 0) {
            $genRevenueToman = (int) round(($genCreditsSpent / $totalTrackCredits) * $totalRevenueToman);
            $promptRevenueToman = (int) round(($promptCreditsSpent / $totalTrackCredits) * $totalRevenueToman);
        } else {
            $genRevenueToman = $totalRevenueToman;
            $promptRevenueToman = 0;
        }

        $usdToTomanRate = (int) SystemSetting::get('usd_to_toman_rate', (int) config('payment.usd_to_toman_rate', 100000));

        // Track 1 Economics (Target margin: 50% - 70%)
        $genCostToman = (int) round($totalCostUsd * $usdToTomanRate);
        $genProfitToman = $genRevenueToman - $genCostToman;
        $genMarginRate = $genRevenueToman > 0
            ? round(($genProfitToman / $genRevenueToman) * 100, 2)
            : 0.0;

        // Track 2 Economics (Target margin: 85% - 95%)
        $promptCostToman = (int) round($promptCostUsd * $usdToTomanRate);
        $promptProfitToman = $promptRevenueToman - $promptCostToman;
        $promptMarginRate = $promptRevenueToman > 0
            ? round(($promptProfitToman / $promptRevenueToman) * 100, 2)
            : 0.0;

        // Blended Gross Margin across all tracks
        $blendedCostUsd = round($totalCostUsd + $promptCostUsd, 4);
        $blendedCostToman = $genCostToman + $promptCostToman;
        $blendedProfitToman = $totalRevenueToman - $blendedCostToman;
        $blendedMarginRate = $totalRevenueToman > 0
            ? round(($blendedProfitToman / $totalRevenueToman) * 100, 2)
            : 0.0;

        return $this->success([
            'overview' => [
                'total_users' => $totalUsers,
                'total_generations' => Generation::count(),
                'completed_generations' => $genCompletedCount,
                'total_prompts_compiled' => $promptCount,
                'in_app_credits_spent' => $genCreditsSpent,
                'prompt_only_credits_spent' => $promptCreditsSpent,
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
                    'cost_toman_estimate' => $genCostToman,
                    'profit_toman_estimate' => $genProfitToman,
                    'margin_percentage' => $genMarginRate,
                    'target' => '> 50%',
                    'status' => $genMarginRate >= 50 ? 'pass' : 'needs_attention',
                ],
            ],
            'unit_economics' => [
                'usd_to_toman_rate' => $usdToTomanRate,
                'in_app_generation' => [
                    'orders_count' => $genCompletedCount,
                    'credits_spent' => $genCreditsSpent,
                    'revenue_toman' => $genRevenueToman,
                    'cost_usd' => $totalCostUsd,
                    'cost_toman' => $genCostToman,
                    'profit_toman' => $genProfitToman,
                    'margin_percentage' => $genMarginRate,
                    'target' => '50% - 70%',
                    'status' => $genMarginRate >= 50 ? 'pass' : 'needs_attention',
                ],
                'prompt_only' => [
                    'prompts_count' => $promptCount,
                    'credits_spent' => $promptCreditsSpent,
                    'revenue_toman' => $promptRevenueToman,
                    'cost_usd' => $promptCostUsd,
                    'cost_toman' => $promptCostToman,
                    'profit_toman' => $promptProfitToman,
                    'margin_percentage' => $promptMarginRate,
                    'target' => '85% - 95%',
                    'status' => $promptMarginRate >= 85 ? 'pass' : 'needs_attention',
                ],
                'blended' => [
                    'revenue_toman' => $totalRevenueToman,
                    'cost_usd' => $blendedCostUsd,
                    'cost_toman' => $blendedCostToman,
                    'profit_toman' => $blendedProfitToman,
                    'margin_percentage' => $blendedMarginRate,
                    'target' => '> 50%',
                    'status' => $blendedMarginRate >= 50 ? 'pass' : 'needs_attention',
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

    public function creditSettings(): JsonResponse
    {
        /** @var array<string, int> $keys */
        $keys = [
            'credit_cost_image_standard' => (int) config('credits.costs.image.standard', 10),
            'credit_cost_image_premium' => (int) config('credits.costs.image.premium', 25),
            'credit_cost_video_base' => (int) config('credits.costs.video.base', 20),
            'credit_cost_video_per_second' => (int) config('credits.costs.video.per_second', 5),
            'credit_cost_prompt_simple' => (int) config('credits.costs.prompt.simple', 1),
            'credit_cost_prompt_image' => (int) config('credits.costs.prompt.image', 2),
            'credit_cost_prompt_video' => (int) config('credits.costs.prompt.video', 5),
        ];

        $current = [];
        foreach ($keys as $key => $default) {
            $val = (int) SystemSetting::get($key, $default);
            $current[$key] = [
                'key' => $key,
                'value' => $val,
                'default' => $default,
                'is_custom' => SystemSetting::query()->where('key', $key)->exists(),
            ];
        }

        return $this->success([
            'rates' => $current,
            'tracks' => [
                'generation' => [
                    'image_standard' => $current['credit_cost_image_standard']['value'],
                    'image_premium' => $current['credit_cost_image_premium']['value'],
                    'video_base' => $current['credit_cost_video_base']['value'],
                    'video_per_second' => $current['credit_cost_video_per_second']['value'],
                ],
                'prompt' => [
                    'simple' => $current['credit_cost_prompt_simple']['value'],
                    'image' => $current['credit_cost_prompt_image']['value'],
                    'video' => $current['credit_cost_prompt_video']['value'],
                ],
            ],
        ]);
    }

    public function updateCreditSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'credit_cost_image_standard' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'credit_cost_image_premium' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'credit_cost_video_base' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'credit_cost_video_per_second' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
            'credit_cost_prompt_simple' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'credit_cost_prompt_image' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'credit_cost_prompt_video' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            if ($value !== null) {
                SystemSetting::set($key, (int) $value, 'integer', 'credits');
            }
        }

        return $this->creditSettings();
    }
}
