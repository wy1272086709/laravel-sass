<?php

use App\Domain\Enums\ApiPermission;
use App\Models\Api\ApiIdempotencyKey;
use App\Models\Api\ApiKey;
use App\Models\Order\Order;
use App\Models\Platform\Package;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use App\Http\Middleware\ApiRateLimitMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ApiRateLimitMiddleware::class);
});

it('rejects signed write endpoints when signature headers are missing', function () {
    [$tenant, $token] = securityApiToken([ApiPermission::OrderManage]);
    $product = Product::factory()->forTenant($tenant)->create(['stock' => 5]);

    $this->withToken($token)
        ->postJson('/api/v1/orders', [
            'buyer_name' => 'Unsigned Buyer',
            'buyer_phone' => '13800138010',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertStatus(401)
        ->assertJsonPath('code', 40103);
});

it('rejects replayed signature nonces on write endpoints', function () {
    [$tenant, $token, $apiKey] = securityApiToken([ApiPermission::OrderManage]);
    $product = Product::factory()->forTenant($tenant)->create(['stock' => 5]);
    $payload = [
        'buyer_name' => 'Replay Buyer',
        'buyer_phone' => '13800138011',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ];

    signedApiJson('POST', '/api/v1/orders', $token, $apiKey, $payload, 'replay-order-1', 'fixed-nonce-001')
        ->assertCreated();

    signedApiJson('POST', '/api/v1/orders', $token, $apiKey, $payload, 'replay-order-2', 'fixed-nonce-001')
        ->assertStatus(409)
        ->assertJsonPath('code', 40902);
});

it('replays the first idempotent response without duplicating an order', function () {
    [$tenant, $token, $apiKey] = securityApiToken([ApiPermission::OrderManage]);
    $product = Product::factory()->forTenant($tenant)->create(['stock' => 5, 'sales_count' => 0]);
    $payload = [
        'buyer_name' => 'Idempotent Buyer',
        'buyer_phone' => '13800138012',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ];

    $firstOrderNo = signedApiJson('POST', '/api/v1/orders', $token, $apiKey, $payload, 'order-idem-001')
        ->assertCreated()
        ->json('data.order_no');

    $secondOrderNo = signedApiJson('POST', '/api/v1/orders', $token, $apiKey, $payload, 'order-idem-001')
        ->assertCreated()
        ->assertHeader('X-Idempotent-Replay', 'true')
        ->json('data.order_no');

    expect($secondOrderNo)->toBe($firstOrderNo)
        ->and(Order::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and($product->refresh()->stock)->toBe(3)
        ->and(ApiIdempotencyKey::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('rejects reusing an idempotency key with a different request body', function () {
    [$tenant, $token, $apiKey] = securityApiToken([ApiPermission::OrderManage]);
    $product = Product::factory()->forTenant($tenant)->create(['stock' => 5]);
    $payload = [
        'buyer_name' => 'First Buyer',
        'buyer_phone' => '13800138013',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ];

    signedApiJson('POST', '/api/v1/orders', $token, $apiKey, $payload, 'order-idem-conflict')
        ->assertCreated();

    $payload['buyer_name'] = 'Different Buyer';

    signedApiJson('POST', '/api/v1/orders', $token, $apiKey, $payload, 'order-idem-conflict')
        ->assertStatus(409)
        ->assertJsonPath('code', 40903);
});

/**
 * @param  array<int, ApiPermission>  $permissions
 * @return array{0: Tenant, 1: string, 2: ApiKey}
 */
function securityApiToken(array $permissions): array
{
    $package = Package::factory()->create(['api_quota_daily' => 1000]);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    $apiKey = ApiKey::factory()->forTenant($tenant)->create([
        'app_key' => 'AK_SECURITY_'.str()->random(8),
        'app_secret' => Hash::make('plain-secret'),
        'signing_secret' => 'plain-secret',
        'permissions' => $permissions,
    ]);

    $response = test()->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'plain-secret',
    ])->assertOk();

    return [$tenant, $response->json('data.access_token'), $apiKey];
}
