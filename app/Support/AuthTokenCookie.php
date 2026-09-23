<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Token auth via HttpOnly cookie instead of localStorage (XSS mitigation).
 * Cookie is raw (never encrypted) and read by AuthenticateFromCookie middleware.
 */
class AuthTokenCookie
{
    public const NAME = 'hale_token';

    public static function minutes(): int
    {
        return max(1, (int) config('auth.token_cookie_minutes', 43200));
    }

    public static function make(Request $request, string $token): Cookie
    {
        return new Cookie(
            self::NAME,
            $token,
            now()->addMinutes(self::minutes()),
            '/',
            null,
            self::secure($request),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }

    public static function forget(Request $request): Cookie
    {
        return new Cookie(
            self::NAME,
            null,
            now()->subYear(),
            '/',
            null,
            self::secure($request),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }

    private static function secure(Request $request): bool
    {
        return (bool) (config('session.secure') ?? $request->isSecure());
    }
}
