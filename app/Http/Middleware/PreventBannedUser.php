<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBannedUser
{
    /**
     * Paths a banned account may still reach: reading the profile (so the
     * client can show a proper message) and logging out safely.
     *
     * @var list<string>
     */
    private const ALLOWED_PATH_PREFIXES = ['api/user/profile', 'api/auth/logout'];

    public function handle(Request $request, Closure $next): Response
    {
        // The default guard is the session-based "web" guard at this point;
        // resolve the sanctum guard explicitly because AuthenticateFromCookie
        // has already converted the cookie into a Bearer header.
        $user = $request->user('sanctum');

        if ($user instanceof User && $user->currentlyBanned()) {
            $path = $request->path();
            $allowed = false;
            foreach (self::ALLOWED_PATH_PREFIXES as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => [
                        'code' => 'ACCOUNT_BANNED',
                        'message' => 'حساب کاربری شما مسدود شده است. برای اطلاعات بیشتر با پشتیبانی تماس بگیرید.',
                    ],
                ], 403);
            }
        }

        return $next($request);
    }
}
