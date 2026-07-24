<?php

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\QueueJobStatus;
use App\Domain\Order\OrderCancellationService;
use App\Infrastructure\Redis\ApiDailyCounter;
use App\Jobs\ApiUsageFlushJob;
use App\Jobs\CloseExpiredOrderJob;
use App\Jobs\InventoryAlertJob;
use App\Jobs\MerchantWelcomeEmailJob;
use App\Jobs\SyncLogisticsJob;
use App\Models\Api\ApiUsageDaily;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Platform\Package;
use App\Models\Product\Product;
use App\Models\System\QueueJobLog;
use App\Models\Tenant\Tenant;
use App\Support\QueueJobLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    if (isset($this->usageTenantId)) {
        app(ApiDailyCounter::class)->reset($this->usageTenantId, '2099-06-30');
    }
});

it('flushes redis api daily counters to the database', function () {
    $package = Package::factory()->create(['api_quota_daily' => 10]);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    $this->usageTenantId = $tenant->id;

    app(ApiDailyCounter::class)->increment($tenant->id, '2099-06-30');
    app(ApiDailyCounter::class)->increment($tenant->id, '2099-06-30');

    app(ApiUsageFlushJob::class, ['usageDate' => '2099-06-30'])->handle(
        app(ApiDailyCounter::class),
        app(QueueJobLogger::class),
    );

    $usage = ApiUsageDaily::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

    expect($usage)->not->toBeNull()
        ->and($usage->request_count)->toBe(2)
        ->and($usage->overage_count)->toBe(0);
});

it('closes expired pending payment orders only', function () {
    $product = Product::factory()->create(['stock' => 3, 'sales_count' => 2]);
    $expired = Order::factory()->forTenant($product->tenant)->create([
        'status' => OrderStatus::PendingPayment,
        'created_at' => now()->subMinutes(31),
    ]);
    OrderItem::factory()->create([
        'tenant_id' => $product->tenant_id,
        'order_id' => $expired->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
    $paid = Order::factory()->create([
        'status' => OrderStatus::Paid,
        'created_at' => now()->subMinutes(31),
    ]);

    app(CloseExpiredOrderJob::class, ['orderId' => $expired->id])->handle(
        app(OrderCancellationService::class),
        app(QueueJobLogger::class),
    );
    app(CloseExpiredOrderJob::class, ['orderId' => $paid->id])->handle(
        app(OrderCancellationService::class),
        app(QueueJobLogger::class),
    );

    expect($expired->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($expired->cancel_reason)->toBe('timeout_unpaid')
        ->and($paid->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($product->refresh()->stock)->toBe(5)
        ->and($product->sales_count)->toBe(0);
});

it('writes queue logs for lightweight operational jobs', function () {
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->forTenant($tenant)->create(['stock' => 3]);
    $order = Order::factory()->forTenant($tenant)->create();

    app(MerchantWelcomeEmailJob::class, ['tenantId' => $tenant->id])->handle(app(QueueJobLogger::class));
    app(InventoryAlertJob::class, ['tenantId' => $tenant->id, 'threshold' => 5])->handle(app(QueueJobLogger::class));
    app(SyncLogisticsJob::class, ['orderId' => $order->id])->handle(app(QueueJobLogger::class));

    expect($product->exists)->toBeTrue()
        ->and(QueueJobLog::query()->withoutGlobalScopes()->where('status', QueueJobStatus::Success)->count())->toBe(3)
        ->and(QueueJobLog::query()->withoutGlobalScopes()->where('name', InventoryAlertJob::class)->first()->payload['low_stock_count'])->toBe(1);
});
