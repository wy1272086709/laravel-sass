<?php

use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\OperationAction;
use App\Domain\Enums\OrderStatus;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\System\OperationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('logs a created audit row when an order is placed', function () {
    Queue::fake();
    [$tenant, $token, $apiKey] = apiTokenForPermissions([ApiPermission::OrderManage]);
    $product = Product::factory()->forTenant($tenant)->create(['price' => 300, 'stock' => 10, 'sales_count' => 0]);

    signedApiJson('POST', '/api/v1/orders', $token, $apiKey, [
        'buyer_name' => 'Charlie',
        'buyer_phone' => '13800138000',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ], 'order-create-audit')
        ->assertCreated();

    $log = OperationLog::query()->withoutGlobalScopes()->where('idempotency_key', 'order-create-audit')->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe(OperationAction::Created)
        ->and($log->actor_kind)->toBe('api_key')
        ->and($log->api_key_id)->toBe($apiKey->id)
        ->and($log->subject_type)->toBe('order')
        ->and($log->from_status)->toBeNull()
        ->and($log->to_status)->toBe(OrderStatus::PendingPayment->value)
        ->and($log->payload['items_count'])->toBe(1)
        ->and($log->payload['total_amount'])->toBe(600.0);
});

it('logs the shipped status transition with from/to status', function () {
    Queue::fake();
    [$tenant, $token, $apiKey] = apiTokenForPermissions([ApiPermission::OrderManage]);
    $order = Order::factory()->forTenant($tenant)->create(['status' => OrderStatus::Paid]);

    signedApiJson('POST', "/api/v1/orders/{$order->order_no}/ship", $token, $apiKey, ['tracking_no' => 'SF123'], 'order-ship-audit')
        ->assertOk();

    $log = OperationLog::query()->withoutGlobalScopes()->where('idempotency_key', 'order-ship-audit')->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe(OperationAction::Shipped)
        ->and($log->from_status)->toBe(OrderStatus::Paid->value)
        ->and($log->to_status)->toBe(OrderStatus::Shipped->value)
        ->and($log->payload['tracking_no'])->toBe('SF123');
});

it('logs refund requests and api-triggered cancellations', function () {
    Queue::fake();
    [$tenant, $token, $apiKey] = apiTokenForPermissions([ApiPermission::OrderManage]);

    $refundOrder = Order::factory()->forTenant($tenant)->create(['status' => OrderStatus::Paid]);
    signedApiJson('POST', "/api/v1/orders/{$refundOrder->order_no}/refund", $token, $apiKey, ['reason' => 'quality'], 'order-refund-audit')
        ->assertOk();

    $refundLog = OperationLog::query()->withoutGlobalScopes()->where('idempotency_key', 'order-refund-audit')->first();
    expect($refundLog)->not->toBeNull()
        ->and($refundLog->action)->toBe(OperationAction::RefundRequested)
        ->and($refundLog->from_status)->toBe(OrderStatus::Paid->value)
        ->and($refundLog->to_status)->toBe(OrderStatus::RefundRequested->value)
        ->and($refundLog->payload['reason'])->toBe('quality');

    $cancelOrder = Order::factory()->forTenant($tenant)->create(['status' => OrderStatus::PendingPayment]);
    signedApiJson('POST', "/api/v1/orders/{$cancelOrder->order_no}/cancel", $token, $apiKey, ['reason' => 'changed mind'], 'order-cancel-audit')
        ->assertOk();

    $cancelLog = OperationLog::query()->withoutGlobalScopes()->where('idempotency_key', 'order-cancel-audit')->first();
    expect($cancelLog)->not->toBeNull()
        ->and($cancelLog->action)->toBe(OperationAction::Cancelled)
        ->and($cancelLog->actor_kind)->toBe('api_key')
        ->and($cancelLog->api_key_id)->toBe($apiKey->id)
        ->and($cancelLog->from_status)->toBe(OrderStatus::PendingPayment->value)
        ->and($cancelLog->to_status)->toBe(OrderStatus::Cancelled->value);
});
