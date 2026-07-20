<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class ApiRequestSigner
{
    public static function signatureFor(Request $request, string $timestamp, string $nonce, string $secret): string
    {
        return hash_hmac('sha256', self::canonicalRequest($request, $timestamp, $nonce), $secret);
    }

    public static function canonicalRequest(Request $request, string $timestamp, string $nonce): string
    {
        return implode("\n", [
            strtoupper($request->getMethod()),
            $request->getPathInfo(),
            self::canonicalQuery($request),
            $timestamp,
            $nonce,
            hash('sha256', $request->getContent() ?: ''),
        ]);
    }

    public static function requestHash(Request $request): string
    {
        return hash('sha256', implode("\n", [
            strtoupper($request->getMethod()),
            $request->getPathInfo(),
            self::canonicalQuery($request),
            hash('sha256', $request->getContent() ?: ''),
        ]));
    }

    private static function canonicalQuery(Request $request): string
    {
        $query = $request->query->all();
        ksort($query);

        return http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
