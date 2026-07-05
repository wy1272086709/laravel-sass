<?php

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Infrastructure\Redis\ApiDailyCounter;
use App\Infrastructure\Redis\KeyResolver;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->tenantId = 888003;
    $this->date = now()->toDateString();
    Route::get('/__api-rate', fn () => response('ok'))->middleware('api.rate');
});

afterEach(function () {
    app(ApiDailyCounter::class)->reset($this->tenantId, $this->date);
});

it('passes when under quota', function () {
    app(ApiDailyCounter::class)->reset($this->tenantId, $this->date);
    bindTenantContext($this->tenantId, PackageTier::Basic);

    $this->get('/__api-rate')->assertOk();
});

it('blocks with a 42901 payload when basic tier reaches quota', function () {
    // 直接把计数预置到 9999，中间件 INCR 后 used=10000 → 命中基础版硬阻断
    Redis::set(KeyResolver::apiDailyCounter($this->tenantId, $this->date), 9999);
    bindTenantContext($this->tenantId, PackageTier::Basic);

    $this->get('/__api-rate')
        ->assertStatus(429)
        ->assertJsonPath('code', 42901)
        ->assertJsonPath('message', 'API daily quota exceeded')
        ->assertJsonPath('data.tier', 'basic')
        ->assertJsonPath('data.quota', 10000)
        ->assertJsonPath('data.used', 10000);
});

it('soft-allows professional tier overage up to 150%', function () {
    // 专业版配额 100000；超额但 < 150000 应软放行
    Redis::set(KeyResolver::apiDailyCounter($this->tenantId, $this->date), 120000);
    bindTenantContext($this->tenantId, PackageTier::Professional);

    $this->get('/__api-rate')->assertOk();
});

it('globally blocks all tiers at 150%', function () {
    // 专业版配额 100000；>= 150000（150%）应全局硬阻断
    Redis::set(KeyResolver::apiDailyCounter($this->tenantId, $this->date), 150000);
    bindTenantContext($this->tenantId, PackageTier::Professional);

    $this->get('/__api-rate')
        ->assertStatus(429)
        ->assertJsonPath('code', 42901);
});

it('does not rate-limit platform view (no tenant)', function () {
    bindTenantContext(null, PackageTier::Basic);

    $this->get('/__api-rate')->assertOk();
});

// —— helpers ——

function bindTenantContext(?int $tenantId, PackageTier $tier): void
{
    app()->instance(
        TenantContext::class,
        new TenantContext($tenantId, null, $tier),
    );
}
