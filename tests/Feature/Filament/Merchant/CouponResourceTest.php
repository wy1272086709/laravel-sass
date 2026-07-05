<?php

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Filament\Merchant\Resources\CouponResource;
use App\Filament\Merchant\Resources\CouponResource\Pages\CreateCoupon;
use App\Models\Marketing\Coupon;
use App\Models\Merchant\MerchantUser;
use App\Models\Tenant\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('merchant'));
});

it('lets merchant users browse only their own coupons', function () {
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();
    $merchant = MerchantUser::factory()->forTenant($tenantA)->create();

    Coupon::factory()->forTenant($tenantA)->create(['name' => '本店优惠券']);
    Coupon::factory()->forTenant($tenantB)->create(['name' => '其他商户优惠券']);

    actingAs($merchant, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenantA->id, null, PackageTier::Basic));

    $this->get(CouponResource::getUrl(panel: 'merchant'))
        ->assertOk()
        ->assertSee('本店优惠券')
        ->assertDontSee('其他商户优惠券');
});

it('creates coupons with the current tenant context', function () {
    $tenant = Tenant::factory()->create();
    $merchant = MerchantUser::factory()->forTenant($tenant)->create();

    actingAs($merchant, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenant->id, null, PackageTier::Basic));

    Livewire::test(CreateCoupon::class)
        ->fillForm([
            'name' => 'Filament 优惠券',
            'type' => 'full_reduction',
            'status' => 'active',
            'discount_value' => 20,
            'min_amount' => 199,
            'usage_limit' => 100,
            'used_count' => 0,
            'starts_at' => now(),
            'ends_at' => now()->addDays(7),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $coupon = Coupon::query()->where('name', 'Filament 优惠券')->firstOrFail();

    expect($coupon->tenant_id)->toBe($tenant->id);
});
