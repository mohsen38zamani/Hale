<?php

namespace App\Domains\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->paginate(min($request->integer('per_page', 20), 50));

        return $this->success([
            'items' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return $this->success(['id' => $item->id, 'read_at' => $item->read_at]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(['message' => 'همه اعلان‌ها خوانده شدند.']);
    }
}
