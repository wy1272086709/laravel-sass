<?php

use App\Domain\Enums\PackageTier;
use App\Models\Api\ApiKey;
use App\Models\Billing\TenantBill;
use App\Models\Marketing\Coupon;
use App\Models\Merchant\MerchantUser;
use App\Models\Order\Order;
use App\Models\Platform\Package;
use App\Models\Platform\PlatformUser;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('migrates the phase 2 core tables and reserved billing fields', function () {
    $tables = [
        'packages',
        'platform_roles',
        'platform_permissions',
        'platform_users',
        'tenants',
        'merchant_users',
        'tenant_settings',
        'products',
        'product_skus',
        'orders',
        'order_items',
        'coupons',
        'api_keys',
        'api_request_logs',
        'api_usage_daily',
        'tenant_bills',
        'reconciliation_discrepancies',
        'risk_rules',
        'risk_alerts',
        'queue_job_logs',
        'package_change_logs',
        'login_logs',
        'impersonation_logs',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Expected table [{$table}] to exist.");
    }

    expect(Schema::hasColumns('tenant_bills', [
        'payment_channel',
        'external_transaction_no',
        'paid_at',
        'payment_meta',
    ]))->toBeTrue();
});

it('seeds packages, admins, tenants and usable merchant sample data', function () {
    $this->seed();

    expect(Package::query()->count())->toBe(3)
        ->and(Package::query()->where('tier', PackageTier::Basic)->exists())->toBeTrue()
        ->and(PlatformUser::query()->where('email', 'admin@saas.test')->exists())->toBeTrue()
        ->and(Tenant::query()->count())->toBe(3)
        ->and(MerchantUser::query()->count())->toBe(3)
        ->and(Product::query()->count())->toBe(9)
        ->and(Order::query()->count())->toBe(3)
        ->and(Coupon::query()->count())->toBe(3)
        ->and(ApiKey::query()->count())->toBe(3)
        ->and(TenantBill::query()->count())->toBe(3);
});
