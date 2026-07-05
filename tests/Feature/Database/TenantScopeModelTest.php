<?php

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('automatically fills tenant_id from the current tenant context', function () {
    $tenant = Tenant::factory()->create();

    app()->instance(TenantContext::class, new TenantContext($tenant->id, null, PackageTier::Basic));

    $product = Product::query()->create([
        'product_code' => 'G-AUTO-001',
        'name' => '自动租户商品',
        'price' => 199.00,
        'stock' => 10,
        'sales_count' => 0,
        'specs' => ['颜色' => '黑色'],
        'status' => 'listed',
    ]);

    expect($product->tenant_id)->toBe($tenant->id);
});

it('filters tenant models by current tenant and leaves platform view unfiltered', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Product::factory()->forTenant($tenantA)->create(['product_code' => 'G-TENANT-A']);
    Product::factory()->forTenant($tenantB)->create(['product_code' => 'G-TENANT-B']);

    app()->instance(TenantContext::class, new TenantContext($tenantA->id, null, PackageTier::Basic));

    expect(Product::query()->pluck('product_code')->all())->toBe(['G-TENANT-A']);

    app()->instance(TenantContext::class, new TenantContext(null, null, PackageTier::Basic));

    expect(Product::query()->orderBy('product_code')->pluck('product_code')->all())
        ->toBe(['G-TENANT-A', 'G-TENANT-B']);
});
