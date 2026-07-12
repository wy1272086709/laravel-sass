<?php

use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\BillStatus;
use App\Models\Api\ApiKey;
use App\Models\Billing\TenantBill;
use App\Models\Platform\Package;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('runs the public api smoke flow from auth to dashboard', function () {
    [$tenant, $token] = stage7ApiSmokeToken([
        ApiPermission::ProductQuery,
        ApiPermission::OrderManage,
        ApiPermission::BillQuery,
        ApiPermission::DashboardRead,
    ]);

    $product = Product::factory()->forTenant($tenant)->create([
        'name' => 'Stage Seven Sample Product',
        'price' => 128.5,
        'stock' => 10,
        'sales_count' => 0,
    ]);

    TenantBill::factory()->forTenant($tenant)->create([
        'billing_period' => '2026-07',
        'transaction_total' => 128.5,
        'commission_amount' => 2.57,
        'api_usage_fee' => 0,
        'api_overage_fee' => 0,
        'total_receivable' => 2.57,
        'status' => BillStatus::PendingSettlement,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('code', 0)
        ->assertJsonPath('meta.total', 1);

    $orderNo = $this->withToken($token)
        ->postJson('/api/v1/orders', [
            'buyer_name' => 'Stage Seven Buyer',
            'buyer_phone' => '13800138007',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('code', 0)
        ->json('data.order_no');

    $this->withToken($token)
        ->getJson("/api/v1/orders/{$orderNo}")
        ->assertOk()
        ->assertJsonPath('data.buyer_name', 'Stage Seven Buyer')
        ->assertJsonCount(1, 'data.items');

    $this->withToken($token)
        ->getJson('/api/v1/bills')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.billing_period', '2026-07');

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/overview')
        ->assertOk()
        ->assertJsonPath('code', 0)
        ->assertJsonStructure([
            'data' => [
                'today_orders',
                'today_amount',
                'api_calls_today',
                'api_quota_daily',
            ],
        ]);
});

/**
 * @param  array<int, ApiPermission>  $permissions
 * @return array{0: Tenant, 1: string, 2: ApiKey}
 */
function stage7ApiSmokeToken(array $permissions): array
{
    $package = Package::factory()->create(['api_quota_daily' => 1000]);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    $apiKey = ApiKey::factory()->forTenant($tenant)->create([
        'app_key' => 'AK_STAGE7_'.str()->random(8),
        'app_secret' => Hash::make('plain-secret'),
        'permissions' => $permissions,
    ]);

    $response = test()->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'plain-secret',
    ])->assertOk();

    return [$tenant, $response->json('data.access_token'), $apiKey];
}
