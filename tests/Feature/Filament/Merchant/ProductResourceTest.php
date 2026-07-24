<?php

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Filament\Merchant\Resources\ProductResource;
use App\Filament\Merchant\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Merchant\MerchantUser;
use App\Models\Product\Product;
use App\Models\Product\ProductSku;
use App\Models\Tenant\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('merchant'));
});

it('lets merchant users browse only their own products', function () {
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();
    $merchant = MerchantUser::factory()->forTenant($tenantA)->create();

    Product::factory()->forTenant($tenantA)->create(['name' => '本店商品']);
    Product::factory()->forTenant($tenantB)->create(['name' => '其他商户商品']);

    actingAs($merchant, 'merchant');

    $this->get(ProductResource::getUrl(panel: 'merchant'))
        ->assertOk()
        ->assertSee('本店商品')
        ->assertDontSee('其他商户商品');
});

it('creates products with the current tenant context', function () {
    $tenant = Tenant::factory()->create();
    $merchant = MerchantUser::factory()->forTenant($tenant)->create();

    actingAs($merchant, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenant->id, null, PackageTier::Basic));

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'product_code' => 'G-FILAMENT-001',
            'name' => 'Filament 商品',
            'price' => 199,
            'stock' => 20,
            'sales_count' => 0,
            'status' => 'listed',
            'specs' => ['颜色' => '黑色'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('product_code', 'G-FILAMENT-001')->firstOrFail();

    expect($product->tenant_id)->toBe($tenant->id);
});

it('creates product skus and aggregates price and stock', function () {
    $tenant = Tenant::factory()->create();
    $merchant = MerchantUser::factory()->forTenant($tenant)->create();

    actingAs($merchant, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenant->id, null, PackageTier::Basic));

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'product_code' => 'G-MULTI-SKU-001',
            'name' => '多规格商品',
            'price' => 0,
            'stock' => 0,
            'sales_count' => 0,
            'status' => 'listed',
            'skus' => [
                ['sku_code' => 'MULTI-RED', 'price' => 88, 'stock' => 3, 'specs' => ['颜色' => '红色']],
                ['sku_code' => 'MULTI-BLUE', 'price' => 99, 'stock' => 5, 'specs' => ['颜色' => '蓝色']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('product_code', 'G-MULTI-SKU-001')->firstOrFail();
    expect((float) $product->price)->toBe(88.0)
        ->and($product->stock)->toBe(8)
        ->and(ProductSku::query()->where('product_id', $product->id)->count())->toBe(2);
});
