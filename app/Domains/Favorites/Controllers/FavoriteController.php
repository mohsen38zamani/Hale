<?php

namespace App\Domains\Favorites\Controllers;

use App\Domains\Favorites\Services\FavoriteService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly FavoriteService $favorites) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:product,generation'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'type.required' => 'نوع موردعلاقه الزامی است.',
            'type.in' => 'نوع موردعلاقه باید محصول یا خروجی باشد.',
            'per_page.integer' => 'تعداد در صفحه باید یک عدد باشد.',
            'per_page.min' => 'حداقل تعداد در صفحه ۱ است.',
            'per_page.max' => 'حداکثر تعداد در صفحه ۵۰ است.',
        ]);

        $items = $this->favorites->queryFor($data['type'])
            ->where('user_id', $request->user()->getKey())
            ->whereIn('id', $this->favorites->ids($request->user(), $data['type']))
            ->with($data['type'] === 'product' ? ['assets'] : ['creativeProject.product', 'outputMedia'])
            ->latest()
            ->paginate($data['per_page'] ?? 15);

        $this->favorites->mark($items->items(), $request->user(), $data['type']);

        return $this->success($items);
    }

    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:product,generation'],
            'id' => ['required', 'integer', 'min:1'],
        ], [
            'type.required' => 'نوع موردعلاقه الزامی است.',
            'type.in' => 'نوع موردعلاقه باید محصول یا خروجی باشد.',
            'id.required' => 'شناسه موردعلاقه الزامی است.',
            'id.integer' => 'شناسه موردعلاقه باید یک عدد باشد.',
            'id.min' => 'شناسه موردعلاقه نامعتبر است.',
        ]);

        $target = $this->favorites->queryFor($data['type'])
            ->whereKey($data['id'])
            ->where('user_id', $request->user()->getKey())
            ->first();

        abort_unless($target, 404);

        $favorited = $this->favorites->toggle($request->user(), $data['type'], (int) $target->getKey());

        return $this->success(['favorited' => $favorited]);
    }
}
