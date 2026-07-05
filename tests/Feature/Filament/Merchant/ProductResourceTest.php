<?php

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Filament\Merchant\Resources\ProductResource;
use App\Filament\Merchant\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Merchant\MerchantUser;
use App\Models\Product\Product;
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
    app()->instance(TenantContext::class, new TenantContext($tenantA->id, null, PackageTier::Basic));

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
