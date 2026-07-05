<?php

use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\PackageTier;
use App\Infrastructure\Redis\ApiDailyCounter;
use App\Infrastructure\Redis\KeyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

afterEach(function () {
    if (isset($this->rateLimitTenantId)) {
        app(ApiDailyCounter::class)->reset($this->rateLimitTenantId, now()->toDateString());
    }
});

it('uses package api_quota_daily for basic tier blocking', function () {
    [$tenant, $token] = apiV1TokenForPermissions([ApiPermission::ProductQuery], PackageTier::Basic, quota: 3);
    $this->rateLimitTenantId = $tenant->id;
    Redis::set(KeyResolver::apiDailyCounter($tenant->id, now()->toDateString()), 2);

    $this->withToken($token)
        ->getJson('/api/v1/products')
        ->assertStatus(429)
        ->assertJsonPath('code', 42901)
        ->assertJsonPath('data.tier', PackageTier::Basic->value)
        ->assertJsonPath('data.quota', 3)
        ->assertJsonPath('data.used', 3);
});

it('allows professional overage before 150 percent and blocks at global threshold', function () {
    [$tenant, $token] = apiV1TokenForPermissions([ApiPermission::ProductQuery], PackageTier::Professional, quota: 10);
    $this->rateLimitTenantId = $tenant->id;

    Redis::set(KeyResolver::apiDailyCounter($tenant->id, now()->toDateString()), 11);
    $this->withToken($token)
        ->getJson('/api/v1/products')
        ->assertOk();

    Redis::set(KeyResolver::apiDailyCounter($tenant->id, now()->toDateString()), 14);
    $this->withToken($token)
        ->getJson('/api/v1/products')
        ->assertStatus(429)
        ->assertJsonPath('data.quota', 10)
        ->assertJsonPath('data.used', 15);
});

it('returns validation errors in the unified envelope on api endpoints', function () {
    [, $token] = apiV1TokenForPermissions([ApiPermission::DashboardRead]);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/trends?days=99')
        ->assertStatus(422)
        ->assertJsonPath('code', 42201)
        ->assertJsonPath('message', 'Validation failed')
        ->assertJsonStructure(['data' => ['days']]);
});
