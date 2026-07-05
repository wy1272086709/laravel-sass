<?php

use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\ProductStatus;
use App\Models\Api\ApiKey;
use App\Models\Platform\Package;
use App\Models\Product\Product;
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
    [, $token] = apiTokenForPermissions([ApiPermission::ProductQuery, ApiPermission::OrderManage]);

    $created = $this->withToken($token)
        ->postJson('/api/v1/products', [
            'name' => 'Handmade Mug',
            'price' => 88.5,
            'stock' => 12,
            'specs' => ['color' => 'white'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Handmade Mug')
        ->assertJsonPath('data.status', ProductStatus::Listed->value)
        ->json('data');

    $this->withToken($token)
        ->putJson("/api/v1/products/{$created['id']}", [
            'price' => 99,
            'stock' => 8,
        ])
        ->assertOk()
        ->assertJsonPath('data.price', 99)
        ->assertJsonPath('data.stock', 8);

    $this->withToken($token)
        ->patchJson("/api/v1/products/{$created['id']}/status", [
            'status' => ProductStatus::Unlisted->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', ProductStatus::Unlisted->value);
});

it('rejects product writes without order_manage permission', function () {
    [, $token] = apiTokenForPermissions([ApiPermission::ProductQuery]);

    $this->withToken($token)
        ->postJson('/api/v1/products', [
            'name' => 'No Permission Product',
            'price' => 10,
            'stock' => 1,
        ])
        ->assertStatus(403)
        ->assertExactJson([
            'code' => 40301,
            'message' => 'Missing required permission order_manage',
            'data' => null,
        ]);
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
        'permissions' => $permissions,
    ]);

    $response = test()->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'plain-secret',
    ]);

    return [$tenant, $response->json('data.access_token'), $apiKey];
}
