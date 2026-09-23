<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->requiresEmailVerification()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'EMAIL_NOT_VERIFIED',
                    'message' => 'ایمیل شما تأیید نشده است. پیش از خرید پلن یا ساخت محتوا، روی لینک ارسال‌شده به ایمیلتان کلیک کنید.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
