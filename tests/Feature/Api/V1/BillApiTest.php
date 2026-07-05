<?php

use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\BillStatus;
use App\Domain\Enums\PackageTier;
use App\Models\Api\ApiKey;
use App\Models\Billing\TenantBill;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists bills for the authenticated tenant only', function () {
    [$tenant, $token] = apiV1TokenForPermissions([ApiPermission::BillQuery]);
    $bill = TenantBill::factory()->forTenant($tenant)->create([
        'billing_period' => '2026-06',
        'total_receivable' => 123.45,
    ]);
    TenantBill::factory()->create(['billing_period' => '2026-06']);

    $this->withToken($token)
        ->getJson('/api/v1/bills?year=2026')
        ->assertOk()
        ->assertJsonPath('code', 0)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.billing_period', $bill->billing_period)
        ->assertJsonPath('data.0.total_receivable', 123.45);
});

it('shows bill details with line items', function () {
    [$tenant, $token] = apiV1TokenForPermissions([ApiPermission::BillQuery]);
    TenantBill::factory()->forTenant($tenant)->create([
        'billing_period' => '2026-06',
        'transaction_total' => 10000,
        'commission_amount' => 200,
        'api_usage_fee' => 30,
        'api_overage_fee' => 10,
        'total_receivable' => 240,
        'merchant_reported_amount' => 239,
        'difference_amount' => 1,
        'status' => BillStatus::PendingSettlement,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/bills/2026-06')
        ->assertOk()
        ->assertJsonPath('data.billing_period', '2026-06')
        ->assertJsonPath('data.merchant_reported_amount', 239)
        ->assertJsonPath('data.difference_amount', 1)
        ->assertJsonPath('data.line_items.0.type', 'commission')
        ->assertJsonPath('data.line_items.2.type', 'api_overage');
});

it('rejects bill reads without bill_query permission', function () {
    [, $token] = apiV1TokenForPermissions([ApiPermission::ProductQuery]);

    $this->withToken($token)
        ->getJson('/api/v1/bills')
        ->assertStatus(403)
        ->assertExactJson([
            'code' => 40301,
            'message' => 'Missing required permission bill_query',
            'data' => null,
        ]);
});

it('returns unified not found envelope for missing bill period', function () {
    [, $token] = apiV1TokenForPermissions([ApiPermission::BillQuery]);

    $this->withToken($token)
        ->getJson('/api/v1/bills/2026-13')
        ->assertStatus(404)
        ->assertExactJson([
            'code' => 40401,
            'message' => 'Resource not found',
            'data' => null,
        ]);
});

/**
 * @param  array<int, ApiPermission>  $permissions
 * @return array{0: Tenant, 1: string, 2: ApiKey}
 */
function apiV1TokenForPermissions(array $permissions, PackageTier $tier = PackageTier::Professional, int $quota = 1000): array
{
    $package = Package::factory()->create([
        'tier' => $tier,
        'api_quota_daily' => $quota,
    ]);
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
