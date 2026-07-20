<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Api\ApiIdempotencyKey;
use App\Models\Api\ApiKey;
use App\Support\ApiRequestSigner;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiIdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($apiKey === null) {
            return ApiResponse::error(40101, 'Invalid or expired AccessToken', 401);
        }

        if ($key === '') {
            return ApiResponse::error(40002, 'Missing Idempotency-Key header', 400);
        }

        if (! preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key)) {
            return ApiResponse::error(40003, 'Invalid Idempotency-Key header', 400);
        }

        $requestHash = ApiRequestSigner::requestHash($request);
        $record = ApiIdempotencyKey::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $apiKey->tenant_id)
            ->where('idempotency_key', $key)
            ->first();

        if ($record !== null) {
            if ($record->request_hash !== $requestHash) {
                return ApiResponse::error(40903, 'Idempotency-Key was reused with a different request', 409);
            }

            if ($record->response_body !== null && $record->status_code !== null) {
                return response($record->response_body, $record->status_code, [
                    'Content-Type' => 'application/json',
                    'X-Idempotent-Replay' => 'true',
                ]);
            }

            return ApiResponse::error(40904, 'Idempotent request is still processing', 409);
        } else {
            $record = ApiIdempotencyKey::query()->create([
                'tenant_id' => $apiKey->tenant_id,
                'api_key_id' => $apiKey->id,
                'idempotency_key' => $key,
                'method' => strtoupper($request->getMethod()),
                'endpoint' => $request->getPathInfo(),
                'request_hash' => $requestHash,
                'expires_at' => now()->addDay(),
            ]);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $record->forceFill([
                'status_code' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ])->save();
        }

        return $response;
    }
}
