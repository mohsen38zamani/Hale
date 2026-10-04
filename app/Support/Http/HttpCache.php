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

        $cleanEtag = (string) preg_replace('/^W\//', '', $etag);
        $candidates = array_map(static fn ($c): string => (string) preg_replace('/^W\//', '', trim($c)), explode(',', $header));

        return in_array($cleanEtag, $candidates, true) || in_array('*', $candidates, true);
    }
}
