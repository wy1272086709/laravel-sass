<?php

use App\Domain\Billing\BillSettlementService;
use App\Domain\Enums\BillStatus;
use App\Models\Api\ApiUsageDaily;
use App\Models\Billing\ReconciliationDiscrepancy;
use App\Models\Billing\TenantBill;
use App\Models\Order\Order;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates monthly bills from orders and api usage', function () {
    $package = Package::factory()->create(['api_quota_daily' => 1]);
    $tenant = Tenant::factory()->create([
        'package_id' => $package->id,
        'commission_rate' => 0.0500,
    ]);

    Order::factory()->forTenant($tenant)->create([
        'total_amount' => 1000,
        'created_at' => '2026-06-10 10:00:00',
    ]);
    Order::factory()->forTenant($tenant)->create([
        'total_amount' => 500,
        'created_at' => '2026-06-20 10:00:00',
    ]);
    Order::factory()->forTenant($tenant)->create([
        'total_amount' => 999,
        'created_at' => '2026-07-01 00:00:00',
    ]);
    ApiUsageDaily::query()->withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'usage_date' => '2026-06-15',
        'request_count' => 40,
        'overage_count' => 10,
    ]);

    $bill = app(BillSettlementService::class)->generateMonthlyBill($tenant->load('package'), '2026-06');

    expect($bill->billing_period)->toBe('2026-06')
        ->and((float) $bill->transaction_total)->toBe(1500.0)
        ->and((float) $bill->commission_amount)->toBe(75.0)
        ->and((float) $bill->api_usage_fee)->toBe(0.0)
        ->and((float) $bill->api_overage_fee)->toBe(0.01)
        ->and((float) $bill->total_receivable)->toBe(75.01)
        ->and($bill->status)->toBe(BillStatus::PendingSettlement);
});

it('creates discrepancy when merchant reported amount differs', function () {
    $bill = TenantBill::factory()->create([
        'total_receivable' => 8420,
        'merchant_reported_amount' => null,
        'difference_amount' => null,
    ]);

    app(BillSettlementService::class)->recordMerchantReport($bill, 8400);

    $discrepancy = ReconciliationDiscrepancy::query()->withoutGlobalScopes()->first();

    expect($discrepancy)->not->toBeNull()
        ->and((float) $discrepancy->difference_amount)->toBe(20.0)
        ->and((float) $bill->refresh()->difference_amount)->toBe(20.0);
});

it('does not create discrepancy when merchant report matches receivable', function () {
    $bill = TenantBill::factory()->create([
        'total_receivable' => 8420,
    ]);

    app(BillSettlementService::class)->recordMerchantReport($bill, 8420);

    expect(ReconciliationDiscrepancy::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and((float) $bill->refresh()->difference_amount)->toBe(0.0);
});
