<?php

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * Tiny ETag helpers shared by the media delivery endpoints.
 */
class HttpCache
{
    public static function etag(mixed ...$parts): string
    {
        return '"'.md5(implode('|', array_map(static fn ($part): string => (string) $part, $parts))).'"';
    }

    public static function notModified(Request $request, string $etag): bool
    {
        $header = trim((string) $request->header('If-None-Match'));
        if ($header === '') {
            return false;
        }

        $candidates = array_map('trim', explode(',', $header));

        return in_array($etag, $candidates, true) || in_array('*', $candidates, true);
    }
}
