<?php

use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\PackageTier;
use App\Models\Api\ApiKey;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('issues access and refresh tokens for valid api key credentials', function () {
    $apiKey = createApiKeyWithSecret('plain-secret');

    $response = $this->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'plain-secret',
    ]);

    $response->assertOk()
        ->assertJsonPath('code', 0)
        ->assertJsonPath('message', 'ok')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.expires_in', 7200)
        ->assertJsonStructure([
            'data' => ['access_token', 'refresh_token'],
        ]);

    expect(PersonalAccessToken::query()->count())->toBe(2)
        ->and($apiKey->refresh()->last_used_at)->not->toBeNull();
});

it('rejects invalid credentials with the unified unauthorized envelope', function () {
    $apiKey = createApiKeyWithSecret('plain-secret');

    $this->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'wrong-secret',
    ])
        ->assertStatus(401)
        ->assertExactJson([
            'code' => 40101,
            'message' => 'Invalid or expired AccessToken',
            'data' => null,
        ]);
});

it('returns validation errors with the unified validation envelope', function () {
    $this->postJson('/api/v1/auth/token', [])
        ->assertStatus(422)
        ->assertJsonPath('code', 42201)
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonStructure(['data' => ['app_key', 'app_secret']]);
});

it('authenticates bearer access tokens and injects the tenant context', function () {
    $apiKey = createApiKeyWithSecret('plain-secret');
    $accessToken = issueTokenPair($apiKey)['access_token'];

    $this->withToken($accessToken)
        ->getJson('/api/v1/ping')
        ->assertOk()
        ->assertJsonPath('data.pong', true)
        ->assertJsonPath('data.tenant_id', $apiKey->tenant_id)
        ->assertJsonPath('data.tier', PackageTier::Professional->value);
});

it('rejects invalid bearer tokens with the unified unauthorized envelope', function () {
    $this->withToken('not-a-real-token')
        ->getJson('/api/v1/ping')
        ->assertStatus(401)
        ->assertExactJson([
            'code' => 40101,
            'message' => 'Invalid or expired AccessToken',
            'data' => null,
        ]);
});

it('refreshes tokens using a refresh token and revokes the used refresh token', function () {
    $apiKey = createApiKeyWithSecret('plain-secret');
    $tokenPair = issueTokenPair($apiKey);

    $this->postJson('/api/v1/auth/token/refresh', [
        'refresh_token' => $tokenPair['refresh_token'],
    ])
        ->assertOk()
        ->assertJsonPath('code', 0)
        ->assertJsonStructure([
            'data' => ['access_token', 'refresh_token'],
        ]);

    expect(PersonalAccessToken::findToken($tokenPair['refresh_token']))->toBeNull();
});

it('revokes the current access token', function () {
    $apiKey = createApiKeyWithSecret('plain-secret');
    $accessToken = issueTokenPair($apiKey)['access_token'];

    $this->withToken($accessToken)
        ->deleteJson('/api/v1/auth/token')
        ->assertOk()
        ->assertExactJson([
            'code' => 0,
            'message' => 'ok',
            'data' => null,
        ]);

    expect(PersonalAccessToken::findToken($accessToken))->toBeNull();
});

function createApiKeyWithSecret(string $secret): ApiKey
{
    $package = Package::factory()->create(['tier' => PackageTier::Professional]);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);

    return ApiKey::factory()->forTenant($tenant)->create([
        'app_key' => 'AK_TEST_'.str()->random(8),
        'app_secret' => Hash::make($secret),
        'permissions' => [ApiPermission::ProductQuery, ApiPermission::OrderManage],
    ]);
}

/** @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int} */
function issueTokenPair(ApiKey $apiKey): array
{
    $response = test()->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'plain-secret',
    ]);

    return $response->json('data');
}
