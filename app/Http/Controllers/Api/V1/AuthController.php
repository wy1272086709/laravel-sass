<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\AccessTokenService;
use App\Http\Controllers\Controller;
use App\Models\Api\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function token(Request $request, AccessTokenService $tokens): JsonResponse
    {
        $data = $request->validate([
            'app_key' => ['required', 'string'],
            'app_secret' => ['required', 'string'],
        ]);

        $tokenPair = $tokens->issueForCredentials($data['app_key'], $data['app_secret']);

        if ($tokenPair === null) {
            return $this->unauthorized();
        }

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => $tokenPair,
        ]);
    }

    public function refresh(Request $request, AccessTokenService $tokens): JsonResponse
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $tokenPair = $tokens->refresh($data['refresh_token']);

        if ($tokenPair === null) {
            return $this->unauthorized();
        }

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => $tokenPair,
        ]);
    }

    public function revoke(Request $request, AccessTokenService $tokens): JsonResponse
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey === null) {
            return $this->unauthorized();
        }

        $tokens->revokeCurrent($apiKey);

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => null,
        ]);
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
