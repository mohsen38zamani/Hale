<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = trim((string) $request->header('X-Request-ID'));
        $requestId = Str::isUuid($header) ? $header : (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        return $next($request)->header('X-Request-ID', $requestId);
    }
}
