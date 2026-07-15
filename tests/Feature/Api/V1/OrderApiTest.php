<?php

use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\OrderStatus;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductSku;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists and shows orders for the authenticated tenant only', function () {
    [$tenant, $token] = apiTokenForPermissions([ApiPermission::OrderManage]);
    $ownOrder = Order::factory()->forTenant($tenant)->create(['buyer_name' => 'Alice']);
    OrderItem::factory()->create(['tenant_id' => $tenant->id, 'order_id' => $ownOrder->id]);
    Order::factory()->create(['buyer_name' => 'Bob']);

    $this->withToken($token)
        ->getJson('/api/v1/orders?per_page=10')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.order_no', $ownOrder->order_no);

    $this->withToken($token)
        ->getJson("/api/v1/orders/{$ownOrder->order_no}")
        ->assertOk()
        ->assertJsonPath('data.buyer_name', 'Alice')
        ->assertJsonCount(1, 'data.items');
});

it('creates orders from tenant products and updates stock snapshots', function () {
    [$tenant, $token] = apiTokenForPermissions([ApiPermission::OrderManage]);
    $product = Product::factory()->forTenant($tenant)->create([
        'name' => 'Standing Desk',
        'price' => 300,
        'stock' => 10,
        'sales_count' => 0,
        'specs' => ['color' => 'oak'],
    ]);

    $response = $this->withToken($token)
        ->postJson('/api/v1/orders', [
            'buyer_name' => 'Charlie',
            'buyer_phone' => '13800138000',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.buyer_name', 'Charlie')
        ->assertJsonPath('data.total_amount', 600)
        ->assertJsonPath('data.status', OrderStatus::PendingPayment->value)
        ->assertJsonPath('data.items.0.product_name', 'Standing Desk');

    $product->refresh();
    expect($product->stock)->toBe(8)
        ->and($product->sales_count)->toBe(2)
        ->and(Order::query()->where('order_no', $response->json('data.order_no'))->exists())->toBeTrue();
});

it('rejects creating orders for products outside the api key tenant', function () {
    [, $token] = apiTokenForPermissions([ApiPermission::OrderManage]);
    $otherProduct = Product::factory()->create();

    $this->withToken($token)
        ->postJson('/api/v1/orders', [
            'buyer_name' => 'Dora',
            'buyer_phone' => '13800138001',
            'items' => [
                ['product_id' => $otherProduct->id, 'quantity' => 1],
            ],
        ])
        ->assertStatus(404)
        ->assertJsonPath('code', 40401);
});

it('ships cancels and requests refunds through valid status transitions', function () {
    [$tenant, $token] = apiTokenForPermissions([ApiPermission::OrderManage]);
    $paidOrder = Order::factory()->forTenant($tenant)->create(['status' => OrderStatus::Paid]);
    $pendingOrder = Order::factory()->forTenant($tenant)->create(['status' => OrderStatus::PendingPayment]);
    $completedOrder = Order::factory()->forTenant($tenant)->create(['status' => OrderStatus::Completed]);

    $this->withToken($token)
        ->postJson("/api/v1/orders/{$paidOrder->order_no}/ship", ['tracking_no' => 'SF123'])
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Shipped->value);

    $this->withToken($token)
        ->postJson("/api/v1/orders/{$pendingOrder->order_no}/cancel", ['reason' => 'buyer changed mind'])
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

    $refundOrder = Order::factory()->forTenant($tenant)->create(['status' => OrderStatus::Paid]);

    $this->withToken($token)
        ->postJson("/api/v1/orders/{$refundOrder->order_no}/refund", ['reason' => 'quality issue'])
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatus::RefundRequested->value);

    $this->withToken($token)
        ->postJson("/api/v1/orders/{$completedOrder->order_no}/ship")
        ->assertStatus(409)
        ->assertJsonPath('code', 40901);
});

it('rejects order endpoints without order_manage permission', function () {
    [, $token] = apiTokenForPermissions([ApiPermission::ProductQuery]);

    $this->withToken($token)
        ->getJson('/api/v1/orders')
        ->assertStatus(403)
        ->assertJsonPath('code', 40301);
});

it('creates orders for a selected sku and deducts sku and product inventory', function () {
    [$tenant, $token] = apiTokenForPermissions([ApiPermission::OrderManage]);
    $product = Product::factory()->forTenant($tenant)->create(['price' => 100, 'stock' => 12, 'sales_count' => 0]);
    $sku = ProductSku::factory()->forProduct($product)->create([
        'sku_code' => 'DESK-WHITE-120',
        'specs' => ['color' => 'white', 'width' => '120cm'],
        'price' => 360,
        'stock' => 7,
    ]);

    $this->withToken($token)
        ->postJson('/api/v1/orders', [
            'buyer_name' => 'SKU Buyer',
            'buyer_phone' => '13800138002',
            'items' => [['product_id' => $product->id, 'sku_id' => $sku->id, 'quantity' => 2]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.total_amount', 720)
        ->assertJsonPath('data.items.0.sku_id', $sku->id)
        ->assertJsonPath('data.items.0.spec_snapshot.color', 'white');

    expect($sku->refresh()->stock)->toBe(5)
        ->and($product->refresh()->stock)->toBe(10)
        ->and($product->sales_count)->toBe(2);
});

it('requires a valid tenant sku when ordering a multi-sku product', function () {
    [$tenant, $token] = apiTokenForPermissions([ApiPermission::OrderManage]);
    $product = Product::factory()->forTenant($tenant)->create(['stock' => 10]);
    ProductSku::factory()->forProduct($product)->create(['stock' => 10]);
    $otherSku = ProductSku::factory()->create();

    $payload = [
        'buyer_name' => 'Invalid SKU Buyer',
        'buyer_phone' => '13800138003',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ];

    $this->withToken($token)->postJson('/api/v1/orders', $payload)
        ->assertStatus(422);

    $payload['items'][0]['sku_id'] = $otherSku->id;
    $this->withToken($token)->postJson('/api/v1/orders', $payload)
        ->assertStatus(404);
});
