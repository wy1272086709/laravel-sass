<?php

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Infrastructure\Octane\OctaneTenantCleanupMiddleware;
use Illuminate\Http\Request;

it('resets the tenant context after the request pipeline', function () {
    // 模拟上一请求残留了一个非空的租户上下文（Octane 常驻下最危险）
    app()->instance(
        TenantContext::class,
        new TenantContext(tenantId: 5, impersonatorId: 9, tier: PackageTier::Professional),
    );

    $middleware = new OctaneTenantCleanupMiddleware();
    $middleware->handle(Request::create('/any'), fn () => response('ok'));

    $context = app(TenantContext::class);

    expect($context->tenantId)->toBeNull()
        ->and($context->impersonatorId)->toBeNull()
        ->and($context->tier)->toBe(PackageTier::Basic);
});

it('always resets even when the downstream throws', function () {
    app()->instance(
        TenantContext::class,
        new TenantContext(tenantId: 7, impersonatorId: null, tier: PackageTier::Enterprise),
    );

    $middleware = new OctaneTenantCleanupMiddleware();

    try {
        $middleware->handle(Request::create('/any'), function () {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException $e) {
        // expected
    }

    expect(app(TenantContext::class)->tenantId)->toBeNull();
});
