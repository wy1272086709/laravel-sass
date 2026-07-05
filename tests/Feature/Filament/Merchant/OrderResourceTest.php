<?php

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Filament\Merchant\Resources\OrderResource;
use App\Filament\Merchant\Resources\OrderResource\Pages\CreateOrder;
use App\Models\Merchant\MerchantUser;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('merchant'));
});

it('lets merchant users browse only their own orders', function () {
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();
    $merchant = MerchantUser::factory()->forTenant($tenantA)->create();

    Order::factory()->forTenant($tenantA)->create(['order_no' => 'ORD-MINE-001']);
    Order::factory()->forTenant($tenantB)->create(['order_no' => 'ORD-OTHER-001']);

    actingAs($merchant, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenantA->id, null, PackageTier::Basic));

    $this->get(OrderResource::getUrl(panel: 'merchant'))
        ->assertOk()
        ->assertSee('ORD-MINE-001')
        ->assertDontSee('ORD-OTHER-001');
});

it('creates orders with the current tenant context', function () {
    $tenant = Tenant::factory()->create();
    $merchant = MerchantUser::factory()->forTenant($tenant)->create();

    actingAs($merchant, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenant->id, null, PackageTier::Basic));

    Livewire::test(CreateOrder::class)
        ->fillForm([
            'order_no' => 'ORD-FILAMENT-001',
            'buyer_name' => '测试买家',
            'buyer_phone' => '13800001111',
            'total_amount' => 299,
            'status' => 'pending_payment',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = Order::query()->where('order_no', 'ORD-FILAMENT-001')->firstOrFail();

    expect($order->tenant_id)->toBe($tenant->id);
});
