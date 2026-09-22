<?php

use App\Domain\Enums\ApiPermission;
use App\Models\Api\ApiKey;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('passes when no ip lists are configured', function () {
    [, $token] = gatewayIpToken();

    $this->withToken($token)->getJson('/api/v1/ping')
        ->assertOk()
        ->assertJsonPath('data.pong', true);
});

it('blocks a blacklisted exact ip', function () {
    [, $token] = gatewayIpToken(blacklist: ['127.0.0.1']);

    $this->withToken($token)->getJson('/api/v1/ping')
        ->assertStatus(403)
        ->assertJsonPath('code', 40302);
});

it('blocks an ip matched by wildcard entry', function () {
    [, $token] = gatewayIpToken(blacklist: ['127.0.*']);

    $this->withToken($token)->getJson('/api/v1/ping')
        ->assertStatus(403)
        ->assertJsonPath('code', 40302);
});

it('blocks an ip matched by cidr entry', function () {
    [, $token] = gatewayIpToken(blacklist: ['127.0.0.0/8']);

    $this->withToken($token)->getJson('/api/v1/ping')
        ->assertStatus(403)
        ->assertJsonPath('code', 40302);
});

it('rejects ips outside a configured whitelist', function () {
    [, $token] = gatewayIpToken(whitelist: ['203.0.113.7']);

    $this->withToken($token)->getJson('/api/v1/ping')
        ->assertStatus(403)
        ->assertJsonPath('code', 40303);
});

it('allows whitelisted ips through', function () {
    [, $token] = gatewayIpToken(whitelist: ['127.0.0.1']);

    $this->withToken($token)->getJson('/api/v1/ping')->assertOk();
});

it('lets the blacklist win over the whitelist', function () {
    [, $token] = gatewayIpToken(whitelist: ['127.0.0.1'], blacklist: ['127.0.0.1']);

    $this->withToken($token)->getJson('/api/v1/ping')
        ->assertStatus(403)
        ->assertJsonPath('code', 40302);
});

it('does not apply ip filtering to the token endpoint itself', function () {
    // token 签发路由不挂 api.ip（此时还没有密钥可用），名单不影响签发
    [, , $apiKey] = gatewayIpToken(blacklist: ['127.0.0.1']);

    $this->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'plain-secret',
    ])->assertOk();
});

/**
 * @param  array<int, string>|null  $whitelist
 * @param  array<int, string>|null  $blacklist
 * @return array{0: Tenant, 1: string, 2: ApiKey}
 */
function gatewayIpToken(?array $whitelist = null, ?array $blacklist = null): array
{
    $package = Package::factory()->create(['api_quota_daily' => 1000]);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    $apiKey = ApiKey::factory()->forTenant($tenant)->create([
        'app_key' => 'AK_IP_'.str()->random(8),
        'app_secret' => Hash::make('plain-secret'),
        'permissions' => [ApiPermission::ProductQuery],
        'ip_whitelist' => $whitelist,
        'ip_blacklist' => $blacklist,
    ]);

    $response = test()->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'plain-secret',
    ])->assertOk();

    return [$tenant, $response->json('data.access_token'), $apiKey];
}
