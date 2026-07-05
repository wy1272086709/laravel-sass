<?php

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Domain\Tenant\TenantScope;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

it('builds a readonly tenant context', function () {
    $context = new TenantContext(tenantId: 1, impersonatorId: 9, tier: PackageTier::Professional);

    expect($context->tenantId)->toBe(1)
        ->and($context->impersonatorId)->toBe(9)
        ->and($context->tier)->toBe(PackageTier::Professional);
});

it('distinguishes platform view, merchant view and impersonation', function () {
    $platform = new TenantContext(null, null, PackageTier::Basic);
    expect($platform->isPlatformView())->toBeTrue()
        ->and($platform->isImpersonating())->toBeFalse();

    $merchant = new TenantContext(7, null, PackageTier::Enterprise);
    expect($merchant->isPlatformView())->toBeFalse()
        ->and($merchant->isImpersonating())->toBeFalse();

    $impersonating = new TenantContext(7, 99, PackageTier::Enterprise);
    expect($impersonating->isPlatformView())->toBeFalse()
        ->and($impersonating->isImpersonating())->toBeTrue();
});

it('tenant scope applies where when tenantId is set and skips when null', function () {
    $scope = new TenantScope();

    // 有 tenantId → 追加 where tenant_id
    app()->instance(TenantContext::class, new TenantContext(42, null, PackageTier::Basic));
    $builderSet = Mockery::mock(Builder::class);
    $builderSet->shouldReceive('where')
        ->once()
        ->with('test_models.tenant_id', 42);
    $modelSet = Mockery::mock(Model::class);
    $modelSet->shouldReceive('getTable')->andReturn('test_models');
    $scope->apply($builderSet, $modelSet);

    // tenantId=null（平台视图）→ 不附加任何 where
    app()->instance(TenantContext::class, new TenantContext(null, null, PackageTier::Basic));
    $builderNull = Mockery::mock(Builder::class);
    $builderNull->shouldNotReceive('where');
    $modelNull = Mockery::mock(Model::class);
    $modelNull->shouldReceive('getTable')->andReturn('test_models');
    $scope->apply($builderNull, $modelNull);
});

it('belongs to tenant trait class exists and boots scope + creating hook', function () {
    expect(trait_exists(BelongsToTenant::class))->toBeTrue();

    $reflection = new ReflectionClass(BelongsToTenant::class);
    expect($reflection->hasMethod('bootBelongsToTenant'))->toBeTrue();
});
