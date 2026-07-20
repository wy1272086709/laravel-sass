<?php

use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\ProductStatus;
use App\Models\Api\ApiKey;
use App\Models\Platform\Package;
use App\Models\Product\Product;
use App\Models\Product\ProductSku;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists products for the authenticated api key tenant only', function () {
    [$tenant, $token] = apiTokenForPermissions([ApiPermission::ProductQuery]);
    $ownProduct = Product::factory()->forTenant($tenant)->create(['name' => 'Tenant Camera']);
    Product::factory()->create(['name' => 'Other Tenant Camera']);

    $this->withToken($token)
        ->getJson('/api/v1/products?keyword=Camera')
        ->assertOk()
        ->assertJsonPath('code', 0)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $ownProduct->id)
        ->assertJsonPath('data.0.name', 'Tenant Camera');
});

it('shows product details and respects tenant isolation', function () {
    [$tenant, $token] = apiTokenForPermissions([ApiPermission::ProductQuery]);
    $ownProduct = Product::factory()->forTenant($tenant)->create();
    $otherProduct = Product::factory()->create();

    $this->withToken($token)
        ->getJson("/api/v1/products/{$ownProduct->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $ownProduct->id);

    $this->withToken($token)
        ->getJson("/api/v1/products/{$otherProduct->id}")
        ->assertStatus(404)
        ->assertJsonPath('code', 40401);
});

it('creates updates and changes product status with order_manage permission', function () {
    [, $token, $apiKey] = apiTokenForPermissions([ApiPermission::ProductQuery, ApiPermission::OrderManage]);

    $created = signedApiJson('POST', '/api/v1/products', $token, $apiKey, [
            'name' => 'Handmade Mug',
            'price' => 88.5,
            'stock' => 12,
            'specs' => ['color' => 'white'],
        ], 'product-create-001')
        ->assertCreated()
        ->assertJsonPath('data.name', 'Handmade Mug')
        ->assertJsonPath('data.status', ProductStatus::Listed->value)
        ->json('data');

    signedApiJson('PUT', "/api/v1/products/{$created['id']}", $token, $apiKey, [
            'price' => 99,
            'stock' => 8,
        ])
        ->assertOk()
        ->assertJsonPath('data.price', 99)
        ->assertJsonPath('data.stock', 8);

    signedApiJson('PATCH', "/api/v1/products/{$created['id']}/status", $token, $apiKey, [
            'status' => ProductStatus::Unlisted->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', ProductStatus::Unlisted->value);
});

it('rejects product writes without order_manage permission', function () {
    [, $token, $apiKey] = apiTokenForPermissions([ApiPermission::ProductQuery]);

    signedApiJson('POST', '/api/v1/products', $token, $apiKey, [
            'name' => 'No Permission Product',
            'price' => 10,
            'stock' => 1,
        ], 'product-create-no-permission')
        ->assertStatus(403)
        ->assertExactJson([
            'code' => 40301,
            'message' => 'Missing required permission order_manage',
            'data' => null,
        ]);
});

it('creates and updates products with multiple skus and aggregated inventory', function () {
    [$tenant, $token, $apiKey] = apiTokenForPermissions([ApiPermission::ProductQuery, ApiPermission::OrderManage]);

    $created = signedApiJson('POST', '/api/v1/products', $token, $apiKey, [
            'name' => 'Multi Color Shirt',
            'price' => 0,
            'stock' => 0,
            'skus' => [
                ['sku_code' => 'SHIRT-BLACK-M', 'specs' => ['color' => 'black', 'size' => 'M'], 'price' => 129, 'stock' => 8],
                ['sku_code' => 'SHIRT-WHITE-L', 'specs' => ['color' => 'white', 'size' => 'L'], 'price' => 139, 'stock' => 5],
            ],
        ], 'product-create-skus')
        ->assertCreated()
        ->assertJsonPath('data.price', 129)
        ->assertJsonPath('data.stock', 13)
        ->assertJsonCount(2, 'data.skus')
        ->json('data');

    $keptSku = $created['skus'][0];
    signedApiJson('PUT', "/api/v1/products/{$created['id']}", $token, $apiKey, [
            'skus' => [
                ['id' => $keptSku['id'], 'sku_code' => $keptSku['sku_code'], 'specs' => $keptSku['specs'], 'price' => 119, 'stock' => 6],
                ['sku_code' => 'SHIRT-BLUE-S', 'specs' => ['color' => 'blue', 'size' => 'S'], 'price' => 149, 'stock' => 4],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.price', 119)
        ->assertJsonPath('data.stock', 10)
        ->assertJsonCount(2, 'data.skus');

    expect(ProductSku::query()->where('product_id', $created['id'])->count())->toBe(2)
        ->and(ProductSku::query()->where('product_id', $created['id'])->pluck('tenant_id')->unique()->all())->toBe([$tenant->id]);
});

/**
 * @param  array<int, ApiPermission>  $permissions
 * @return array{0: Tenant, 1: string, 2: ApiKey}
 */
function apiTokenForPermissions(array $permissions): array
{
    $package = Package::factory()->create();
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    $apiKey = ApiKey::factory()->forTenant($tenant)->create([
        'app_key' => 'AK_TEST_'.str()->random(8),
        'app_secret' => Hash::make('plain-secret'),
        'signing_secret' => 'plain-secret',
        'permissions' => $permissions,
    ]);

    $response = test()->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'plain-secret',
    ]);

    return [$tenant, $response->json('data.access_token'), $apiKey];
}
