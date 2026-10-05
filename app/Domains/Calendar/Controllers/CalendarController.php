<?php

namespace App\Domains\Calendar\Controllers;

use App\Domains\Calendar\Models\Campaign;
use App\Domains\Calendar\Models\ScheduledPost;
use App\Domains\Generations\Models\Generation;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
    use ApiResponse;

    /**
     * Scheduled posts in the requested range (defaults to everything so a
     * client can page the calendar itself). Everything is user-scoped.
     */
    public function posts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [
            'from.date' => 'تاریخ شروع بازه نامعتبر است.',
            'to.date' => 'تاریخ پایان بازه نامعتبر است.',
            'to.after_or_equal' => 'تاریخ پایان باید بعد یا مساوی تاریخ شروع باشد.',
        ]);

        $posts = $request->user()->scheduledPosts()
            ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('scheduled_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('scheduled_at', '<=', $to))
            ->with(['generation.creativeProject.product', 'campaign'])
            ->orderBy('scheduled_at')
            ->limit(500)
            ->get();

        return $this->success($posts->map(fn (ScheduledPost $post): array => $this->postPayload($post)));
    }

    public function storePost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'generation_id' => ['required', 'integer'],
            'scheduled_at' => ['required', 'date', 'after_or_equal:today'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:'.implode(',', ScheduledPost::STATUSES)],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'generation_id.required' => 'انتخاب خروجی الزامی است.',
            'scheduled_at.required' => 'تاریخ انتشار الزامی است.',
            'scheduled_at.after_or_equal' => 'تاریخ انتشار نمی‌تواند در گذشته باشد.',
            'caption.max' => 'کپشن حداکثر ۲۰۰۰ کاراکتر است.',
            'status.in' => 'وضعیت پست نامعتبر است.',
            'notes.max' => 'یادداشت حداکثر ۵۰۰ کاراکتر است.',
        ]);

        $generation = Generation::query()->find($data['generation_id']);
        abort_unless($generation !== null && $generation->user_id === $request->user()->id, 404);

        $post = $request->user()->scheduledPosts()->create([
            'generation_id' => $generation->id,
            'scheduled_at' => $data['scheduled_at'],
            'caption' => $data['caption'] ?? null,
            'status' => $data['status'] ?? 'scheduled',
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->success($this->postPayload($post->load(['generation.creativeProject.product', 'campaign'])), 201);
    }

    public function updatePost(Request $request, ScheduledPost $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'caption' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', ScheduledPost::STATUSES)],
            'scheduled_at' => ['sometimes', 'date', 'after_or_equal:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ], [
            'caption.max' => 'کپشن حداکثر ۲۰۰۰ کاراکتر است.',
            'status.in' => 'وضعیت پست نامعتبر است.',
            'scheduled_at.after_or_equal' => 'تاریخ انتشار نمی‌تواند در گذشته باشد.',
            'notes.max' => 'یادداشت حداکثر ۵۰۰ کاراکتر است.',
        ]);

        $post->update($data);

        return $this->success($this->postPayload($post->fresh()->load(['generation.creativeProject.product', 'campaign'])));
    }

    public function destroyPost(Request $request, ScheduledPost $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 404);

        $post->delete();

        return $this->success(['deleted' => true]);
    }

    /**
     * A campaign fans one shared start date out into stepwise scheduled
     * posts: post i lands on start_date + i * interval_days.
     */
    public function storeCampaign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'generation_ids' => ['required', 'array', 'min:1', 'max:30'],
            'generation_ids.*' => ['integer'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'interval_days' => ['required', 'integer', 'between:1,30'],
        ], [
            'name.required' => 'نام کمپین الزامی است.',
            'name.max' => 'نام کمپین حداکثر ۱۰۰ کاراکتر است.',
            'generation_ids.required' => 'انتخاب حداقل یک خروجی الزامی است.',
            'generation_ids.min' => 'انتخاب حداقل یک خروجی الزامی است.',
            'generation_ids.max' => 'حداکثر ۳۰ خروجی در هر کمپین قابل زمان‌بندی است.',
            'start_date.required' => 'تاریخ شروع کمپین الزامی است.',
            'start_date.after_or_equal' => 'تاریخ شروع کمپین نمی‌تواند در گذشته باشد.',
            'interval_days.between' => 'فاصلهٔ انتشار باید بین ۱ تا ۳۰ روز باشد.',
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['generation_ids'])));
        $owned = Generation::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
        abort_unless(count($owned) === count($ids), 404, 'یکی از خروجی‌های انتخاب‌شده یافت نشد.');

        $campaign = DB::transaction(function () use ($request, $data, $ids): Campaign {
            $campaign = $request->user()->campaigns()->create([
                'name' => $data['name'],
                'starts_at' => $data['start_date'],
                'interval_days' => (int) $data['interval_days'],
            ]);

            $start = Carbon::parse($data['start_date']);
            foreach ($ids as $index => $generationId) {
                $request->user()->scheduledPosts()->create([
                    'campaign_id' => $campaign->id,
                    'generation_id' => $generationId,
                    'scheduled_at' => $start->copy()->addDays($index * (int) $data['interval_days']),
                    'status' => 'scheduled',
                ]);
            }

            return $campaign;
        });

        $posts = $campaign->posts()->with(['generation.creativeProject.product', 'campaign'])->orderBy('scheduled_at')->get();

        return $this->success([
            'id' => $campaign->id,
            'name' => $campaign->name,
            'starts_at' => $campaign->starts_at->toDateString(),
            'interval_days' => $campaign->interval_days,
            'posts_count' => $posts->count(),
            'posts' => $posts->map(fn (ScheduledPost $post): array => $this->postPayload($post)),
        ], 201);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $campaigns = $request->user()->campaigns()
            ->withCount('posts')
            ->with('posts')
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get()
            ->map(function (Campaign $campaign): array {
                $dates = $campaign->posts
                    ->map(fn (ScheduledPost $post): string => $post->scheduled_at->toDateString())
                    ->sort()
                    ->values();

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'starts_at' => $campaign->starts_at->toDateString(),
                    'interval_days' => $campaign->interval_days,
                    'posts_count' => (int) $campaign->posts_count,
                    'scheduled_at_range' => $dates->count() > 0
                        ? ['first' => (string) $dates->first(), 'last' => (string) $dates->last()]
                        : null,
                ];
            });

        return $this->success($campaigns);
    }

    /**
     * Stable wire shape: date-only scheduled_at plus just enough of the
     * generation to link and label the calendar row.
     *
     * @return array<string, mixed>
     */
    private function postPayload(ScheduledPost $post): array
    {
        $generation = $post->generation;

        return [
            'id' => $post->id,
            'scheduled_at' => $post->scheduled_at->toDateString(),
            'status' => $post->status,
            'caption' => $post->caption,
            'notes' => $post->notes,
            'campaign' => $post->campaign !== null
                ? ['id' => $post->campaign->id, 'name' => $post->campaign->name]
                : null,
            'generation' => $generation !== null
                ? [
                    'id' => $generation->id,
                    'type' => $generation->type,
                    'status' => $generation->status,
                    'product' => $generation->creativeProject?->product?->name,
                ]
                : null,
        ];
    }
}
