<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Api\ApiKey;
use App\Support\ApiRequestSigner;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiSignatureMiddleware
{
    private const ALLOWED_DRIFT_SECONDS = 300;

    public function handle(Request $request, Closure $next): mixed
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey === null || blank($apiKey->signing_secret)) {
            return ApiResponse::error(40102, 'Missing API signing secret', 401);
        }

        $appKey = (string) $request->header('X-App-Key', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');
        $signature = (string) $request->header('X-Signature', '');

        if ($appKey === '' || $timestamp === '' || $nonce === '' || $signature === '') {
            return ApiResponse::error(40103, 'Missing API signature headers', 401);
        }

        if ($appKey !== $apiKey->app_key) {
            return ApiResponse::error(40104, 'API signature app key mismatch', 401);
        }

        if (! ctype_digit($timestamp) || abs(now()->timestamp - (int) $timestamp) > self::ALLOWED_DRIFT_SECONDS) {
            return ApiResponse::error(40105, 'API signature timestamp expired', 401);
        }

        if (! preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $nonce)) {
            return ApiResponse::error(40106, 'Invalid API signature nonce', 401);
        }

        $expected = ApiRequestSigner::signatureFor($request, $timestamp, $nonce, (string) $apiKey->signing_secret);
        if (! hash_equals($expected, $signature)) {
            return ApiResponse::error(40107, 'Invalid API signature', 401);
        }

        if (! $this->reserveNonce($apiKey, $nonce)) {
            return ApiResponse::error(40902, 'API signature nonce has already been used', 409);
        }

        return $next($request);
    }

    private function reserveNonce(ApiKey $apiKey, string $nonce): bool
    {
        DB::table('api_signature_nonces')
            ->where('tenant_id', $apiKey->tenant_id)
            ->where('api_key_id', $apiKey->id)
            ->where('expires_at', '<', now())
            ->delete();

        try {
            DB::table('api_signature_nonces')->insert([
                'tenant_id' => $apiKey->tenant_id,
                'api_key_id' => $apiKey->id,
                'nonce' => $nonce,
                'expires_at' => now()->addSeconds(self::ALLOWED_DRIFT_SECONDS),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (QueryException) {
            return false;
        }
    }
}
