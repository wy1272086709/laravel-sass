<?php

use App\Domain\Billing\BillSettlementService;
use App\Domain\Enums\BillStatus;
use App\Infrastructure\Redis\LuaDistributedLock;
use App\Jobs\MonthlyBillingJob;
use App\Models\Billing\TenantBill;
use App\Models\Order\Order;
use App\Models\System\QueueJobLog;
use App\Models\Tenant\Tenant;
use App\Support\QueueJobLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates monthly bills for tenants and is idempotent', function () {
    $tenantA = Tenant::factory()->create(['commission_rate' => 0.0200]);
    $tenantB = Tenant::factory()->create(['commission_rate' => 0.0300]);

    Order::factory()->forTenant($tenantA)->create(['total_amount' => 1000, 'created_at' => '2026-06-10 10:00:00']);
    Order::factory()->forTenant($tenantB)->create(['total_amount' => 2000, 'created_at' => '2026-06-10 10:00:00']);

    app(MonthlyBillingJob::class, ['period' => '2026-06'])->handle(
        app(BillSettlementService::class),
        app(LuaDistributedLock::class),
        app(QueueJobLogger::class),
    );
    app(MonthlyBillingJob::class, ['period' => '2026-06'])->handle(
        app(BillSettlementService::class),
        app(LuaDistributedLock::class),
        app(QueueJobLogger::class),
    );

    expect(TenantBill::query()->withoutGlobalScopes()->where('billing_period', '2026-06')->count())->toBe(2)
        ->and((float) TenantBill::query()->withoutGlobalScopes()->where('tenant_id', $tenantA->id)->first()->commission_amount)->toBe(20.0)
        ->and((float) TenantBill::query()->withoutGlobalScopes()->where('tenant_id', $tenantB->id)->first()->commission_amount)->toBe(60.0)
        ->and(QueueJobLog::query()->withoutGlobalScopes()->where('name', MonthlyBillingJob::class)->count())->toBe(4);
});

it('preserves a settled bill status when recalculating amounts', function () {
    $tenant = Tenant::factory()->create(['commission_rate' => 0.0200]);
    Order::factory()->forTenant($tenant)->create(['total_amount' => 1000, 'created_at' => '2026-06-10 10:00:00']);
    $bill = TenantBill::factory()->forTenant($tenant)->create([
        'billing_period' => '2026-06',
        'status' => BillStatus::Settled,
        'commission_amount' => 1,
    ]);

    app(BillSettlementService::class)->generateMonthlyBill($tenant->load('package'), '2026-06');

    expect($bill->refresh()->status)->toBe(BillStatus::Settled)
        ->and((float) $bill->commission_amount)->toBe(20.0);
});
