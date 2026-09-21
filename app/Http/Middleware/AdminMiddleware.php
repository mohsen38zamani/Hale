<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'ابتدا وارد حساب کاربری شوید.'],
            ], 401);
        }

        $adminEmails = array_filter(array_map('trim', explode(',', (string) config('auth.admin_emails', ''))));

        if (empty($adminEmails) || ! in_array($user->email, $adminEmails, true)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'FORBIDDEN', 'message' => 'دسترسی به بخش مدیریت مجاز نیست.'],
            ], 403);
        }

        return $next($request);
    }
}
