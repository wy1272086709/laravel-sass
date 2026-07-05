<?php

declare(strict_types=1);

namespace App\Domain\Api;

use App\Domain\Enums\ApiKeyStatus;
use App\Domain\Enums\ApiPermission;
use App\Models\Api\ApiKey;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

final class AccessTokenService
{
    public const ACCESS_TOKEN_NAME = 'api-access';

    public const REFRESH_TOKEN_NAME = 'api-refresh';

    public const ACCESS_TOKEN_TTL_MINUTES = 120;

    public const REFRESH_TOKEN_TTL_MINUTES = 60 * 24 * 30;

    public function issueForCredentials(string $appKey, string $appSecret): ?array
    {
        $apiKey = ApiKey::query()
            ->where('app_key', $appKey)
            ->where('status', ApiKeyStatus::Enabled)
            ->first();

        if ($apiKey === null || ! Hash::check($appSecret, $apiKey->app_secret)) {
            return null;
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();

        return $this->tokenPair($apiKey);
    }

    public function refresh(string $refreshToken): ?array
    {
        $accessToken = PersonalAccessToken::findToken($refreshToken);

        if ($accessToken === null || $accessToken->name !== self::REFRESH_TOKEN_NAME || self::isTokenExpired($accessToken)) {
            return null;
        }

        $apiKey = $accessToken->tokenable;

        if (! $apiKey instanceof ApiKey || $apiKey->status !== ApiKeyStatus::Enabled) {
            return null;
        }

        $accessToken->delete();

        return $this->tokenPair($apiKey);
    }

    public function revokeCurrent(ApiKey $apiKey): void
    {
        $token = $apiKey->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }
    }

    public static function isTokenExpired(PersonalAccessToken $token): bool
    {
        return $token->expires_at !== null && $token->expires_at->isPast();
    }

    private function tokenPair(ApiKey $apiKey): array
    {
        $accessToken = $apiKey->createToken(
            self::ACCESS_TOKEN_NAME,
            $this->abilitiesFor($apiKey),
            now()->addMinutes(self::ACCESS_TOKEN_TTL_MINUTES),
        );

        $refreshToken = $apiKey->createToken(
            self::REFRESH_TOKEN_NAME,
            ['refresh'],
            now()->addMinutes(self::REFRESH_TOKEN_TTL_MINUTES),
        );

        return [
            'access_token' => $this->plainTextToken($accessToken),
            'refresh_token' => $this->plainTextToken($refreshToken),
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL_MINUTES * 60,
        ];
    }

    /** @return array<int, string> */
    private function abilitiesFor(ApiKey $apiKey): array
    {
        return collect($apiKey->permissions)
            ->map(fn (mixed $permission): string => $permission instanceof ApiPermission ? $permission->value : (string) $permission)
            ->values()
            ->all();
    }

    private function plainTextToken(NewAccessToken $token): string
    {
        return $token->plainTextToken;
    }
}
