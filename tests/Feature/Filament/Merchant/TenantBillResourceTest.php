<?php

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Filament\Merchant\Resources\TenantBillResource;
use App\Filament\Merchant\Resources\TenantBillResource\Pages\EditTenantBill;
use App\Models\Billing\TenantBill;
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

it('lets merchant users browse only their own bills', function () {
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();
    $merchant = MerchantUser::factory()->forTenant($tenantA)->create();

    TenantBill::factory()->forTenant($tenantA)->create(['billing_period' => '2026-06']);
    TenantBill::factory()->forTenant($tenantB)->create(['billing_period' => '2026-07']);

    actingAs($merchant, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenantA->id, null, PackageTier::Basic));

    $this->get(TenantBillResource::getUrl(panel: 'merchant'))
        ->assertOk()
        ->assertSee('2026-06')
        ->assertDontSee('2026-07');
});

it('updates reserved payment fields on bills', function () {
    $tenant = Tenant::factory()->create();
    $merchant = MerchantUser::factory()->forTenant($tenant)->create();
    $bill = TenantBill::factory()->forTenant($tenant)->create(['billing_period' => '2026-06']);

    actingAs($merchant, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenant->id, null, PackageTier::Basic));

    Livewire::test(EditTenantBill::class, ['record' => $bill->getRouteKey()])
        ->fillForm([
            'payment_channel' => 'offline_bank',
            'external_transaction_no' => 'PAY-001',
            'paid_at' => now(),
            'payment_meta' => ['operator' => 'finance'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($bill->refresh()->payment_channel)->toBe('offline_bank')
        ->and($bill->external_transaction_no)->toBe('PAY-001')
        ->and($bill->payment_meta)->toBe(['operator' => 'finance']);
});
