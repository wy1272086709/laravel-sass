<?php

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Filament\Merchant\Pages\MerchantDashboardPage;
use App\Models\Billing\TenantBill;
use App\Models\Marketing\Coupon;
use App\Models\Merchant\MerchantUser;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('merchant'));
});

it('renders merchant dashboard metrics for the current tenant only', function () {
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();
    $merchant = MerchantUser::factory()->forTenant($tenantA)->create();

    Product::factory()->forTenant($tenantA)->create(['name' => '本店商品']);
    Product::factory()->forTenant($tenantB)->create(['name' => '其他商品']);
    Order::factory()->forTenant($tenantA)->create(['order_no' => 'ORD-DASH-001', 'total_amount' => 300]);
    Order::factory()->forTenant($tenantB)->create(['order_no' => 'ORD-DASH-OTHER', 'total_amount' => 999]);
    Coupon::factory()->forTenant($tenantA)->create();
    TenantBill::factory()->forTenant($tenantA)->create();

    actingAs($merchant, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenantA->id, null, PackageTier::Basic));

    $this->get(MerchantDashboardPage::getUrl(panel: 'merchant'))
        ->assertOk()
        ->assertSee('店铺概览')
        ->assertSee('ORD-DASH-001')
        ->assertDontSee('ORD-DASH-OTHER')
        ->assertDontSee('其他商品');
});
