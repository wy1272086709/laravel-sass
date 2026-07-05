<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Api\AccessTokenService;
use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Models\Api\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class ApiAuthMiddleware
{
    public function handle(Request $request, Closure $next, ?string $permission = null): mixed
    {
        $token = $this->resolveToken($request);

        if ($token === null || $token->name !== AccessTokenService::ACCESS_TOKEN_NAME || AccessTokenService::isTokenExpired($token)) {
            return $this->unauthorized();
        }

        $apiKey = $token->tokenable;

        if (! $apiKey instanceof ApiKey) {
            return $this->unauthorized();
        }

        if ($permission !== null && ! $token->can($permission)) {
            return response()->json([
                'code' => 40301,
                'message' => "Missing required permission {$permission}",
                'data' => null,
            ], 403);
        }

        $tenant = $apiKey->tenant()->with('package')->first();
        $tier = $tenant?->package?->tier ?? PackageTier::Basic;

        app()->instance(TenantContext::class, new TenantContext($apiKey->tenant_id, null, $tier));
        $request->attributes->set('api_key', $apiKey);
        $apiKey->withAccessToken($token);

        return $next($request);
    }

    private function resolveToken(Request $request): ?PersonalAccessToken
    {
        $bearer = $request->bearerToken();

        return $bearer === null ? null : PersonalAccessToken::findToken($bearer);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'code' => 40101,
            'message' => 'Invalid or expired AccessToken',
            'data' => null,
        ], 401);
    }
}
