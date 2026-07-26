<?php

use App\Domain\Enums\OperationAction;
use App\Domain\Enums\OrderStatus;
use App\Domain\Order\OrderCancellationService;
use App\Jobs\CloseExpiredOrderJob;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\System\OperationLog;
use App\Models\Tenant\Tenant;
use App\Support\QueueJobLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs a system cancel audit row when the expired order job closes an order', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->forTenant($tenant)->create([
        'status' => OrderStatus::PendingPayment,
        'created_at' => now()->subHour(),
    ]);
    OrderItem::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $order->id]);

    $job = new CloseExpiredOrderJob($order->id);
    $job->handle(app(OrderCancellationService::class), app(QueueJobLogger::class));

    $log = OperationLog::query()->withoutGlobalScopes()
        ->where('subject_type', 'order')
        ->where('subject_id', $order->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe(OperationAction::Cancelled)
        ->and($log->actor_kind)->toBe('system')
        ->and($log->actor_label)->toBe('系统超时关单')
        ->and($log->from_status)->toBe(OrderStatus::PendingPayment->value)
        ->and($log->to_status)->toBe(OrderStatus::Cancelled->value)
        ->and($log->payload['reason'])->toBe('timeout_unpaid')
        ->and($log->payload['only_if_expired'])->toBeTrue();
});

it('does not log when the order is too fresh to be closed', function () {
    $tenant = Tenant::factory()->create();
    $order = Order::factory()->forTenant($tenant)->create([
        'status' => OrderStatus::PendingPayment,
        'created_at' => now()->subMinute(),
    ]);

    $job = new CloseExpiredOrderJob($order->id);
    $job->handle(app(OrderCancellationService::class), app(QueueJobLogger::class));

    expect(OperationLog::query()->withoutGlobalScopes()->where('subject_id', $order->id)->count())->toBe(0);
});
