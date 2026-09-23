<?php

namespace App\Http\Middleware;

use App\Support\AuthTokenCookie;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Promotes the HttpOnly auth cookie to a Bearer header so `auth:sanctum`
 * can authenticate cookie-based (PWA/web) requests. An explicit Authorization
 * header always wins, keeping API token clients unaffected.
 */
class AuthenticateFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->has('Authorization')) {
            $token = $request->cookies->get(AuthTokenCookie::NAME);
            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
