<?php

namespace Database\Seeders;

use App\Domain\Enums\PackageTier;
use App\Models\Platform\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'tier' => PackageTier::Basic,
                'name' => '基础版',
                'price_monthly' => 99.00,
                'api_quota_daily' => 10_000,
                'merchant_limit' => 1,
                'features' => [
                    'product_management' => true,
                    'order_management' => true,
                    'api_access' => true,
                    'overage_billing' => false,
                ],
            ],
            [
                'tier' => PackageTier::Professional,
                'name' => '专业版',
                'price_monthly' => 399.00,
                'api_quota_daily' => 100_000,
                'merchant_limit' => 3,
                'features' => [
                    'product_management' => true,
                    'order_management' => true,
                    'api_access' => true,
                    'overage_billing' => true,
                    'risk_alerts' => true,
                ],
            ],
            [
                'tier' => PackageTier::Enterprise,
                'name' => '企业版',
                'price_monthly' => 1299.00,
                'api_quota_daily' => 1_000_000,
                'merchant_limit' => 10,
                'features' => [
                    'product_management' => true,
                    'order_management' => true,
                    'api_access' => true,
                    'overage_billing' => true,
                    'risk_alerts' => true,
                    'priority_support' => true,
                ],
            ],
        ];

        foreach ($packages as $attributes) {
            Package::query()->updateOrCreate(
                ['tier' => $attributes['tier']],
                $attributes + ['is_active' => true],
            );
        }
    }
}
