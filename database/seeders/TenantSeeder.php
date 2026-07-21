<?php

namespace Database\Seeders;

use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\BillStatus;
use App\Domain\Enums\CouponStatus;
use App\Domain\Enums\CouponType;
use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\PackageTier;
use App\Domain\Enums\ProductStatus;
use App\Models\Api\ApiKey;
use App\Models\Billing\TenantBill;
use App\Models\Marketing\Coupon;
use App\Models\Merchant\MerchantUser;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Platform\Package;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'merchant_code' => 'MHT-10001',
                'name' => '星河数码旗舰店',
                'contact_name' => '林夏',
                'contact_phone' => '13900001001',
                'tier' => PackageTier::Basic,
                'email' => 'merchant-basic@saas.test',
            ],
            [
                'merchant_code' => 'MHT-10002',
                'name' => '青禾生活馆',
                'contact_name' => '周宁',
                'contact_phone' => '13900001002',
                'tier' => PackageTier::Professional,
                'email' => 'merchant-pro@saas.test',
            ],
            [
                'merchant_code' => 'MHT-10003',
                'name' => '云帆企业采购',
                'contact_name' => '许舟',
                'contact_phone' => '13900001003',
                'tier' => PackageTier::Enterprise,
                'email' => 'merchant-enterprise@saas.test',
            ],
        ];

        foreach ($tenants as $index => $data) {
            $package = Package::query()->where('tier', $data['tier'])->firstOrFail();

            $tenant = Tenant::query()->updateOrCreate(
                ['merchant_code' => $data['merchant_code']],
                [
                    'name' => $data['name'],
                    'contact_name' => $data['contact_name'],
                    'contact_phone' => $data['contact_phone'],
                    'package_id' => $package->id,
                    'status' => 'enabled',
                    'commission_rate' => 0.0200,
                    'joined_at' => now()->subDays(30 - ($index * 3)),
                ],
            );

            MerchantUser::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $data['contact_name'],
                    'phone' => $data['contact_phone'],
                    'password' => 'password',
                    'is_active' => true,
                ],
            );

            $products = collect([
                ['name' => '轻量机械键盘', 'product_code' => 'G-'.$tenant->id.'001', 'price' => 299.00, 'stock' => 120, 'sales_count' => 494],
                ['name' => '智能保温杯', 'product_code' => 'G-'.$tenant->id.'002', 'price' => 129.00, 'stock' => 240, 'sales_count' => 286],
                ['name' => '无线降噪耳机', 'product_code' => 'G-'.$tenant->id.'003', 'price' => 499.00, 'stock' => 80, 'sales_count' => 168],
            ])->map(fn (array $attributes): Product => Product::withTrashed()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'product_code' => $attributes['product_code'],
                ],
                $attributes + [
                    'tenant_id' => $tenant->id,
                    'cover_image' => null,
                    'specs' => ['颜色' => '默认'],
                    'status' => ProductStatus::Listed,
                    'deleted_at' => null,
                ],
            ));

            $orderNo = sprintf('ORD%s%04d', now()->format('Ymd'), $tenant->id);

            $paidOrder = Order::query()->updateOrCreate(
                ['order_no' => $orderNo],
                [
                    'tenant_id' => $tenant->id,
                    'buyer_name' => '演示买家'.$tenant->id,
                    'buyer_phone' => '1370000'.str_pad((string) $tenant->id, 4, '0', STR_PAD_LEFT),
                    'status' => OrderStatus::Paid,
                    'total_amount' => 598.00,
                    'paid_at' => now()->subDays(2),
                ],
            );

            OrderItem::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'order_id' => $paidOrder->id,
                'product_id' => $products->first()->id,
            ], [
                'tenant_id' => $tenant->id,
                'order_id' => $paidOrder->id,
                'product_id' => $products->first()->id,
                'product_name' => $products->first()->name,
                'unit_price' => 299.00,
                'quantity' => 2,
                'spec_snapshot' => ['颜色' => '墨黑'],
            ]);

            Coupon::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => '新客满减券',
                ],
                [
                    'type' => CouponType::FullReduction,
                    'status' => CouponStatus::Active,
                    'discount_value' => 20.00,
                    'min_amount' => 199.00,
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addDays(30),
                    'usage_limit' => 200,
                    'used_count' => 0,
                ],
            );

            ApiKey::query()->updateOrCreate(
                ['app_key' => 'AK_DEMO_'.$tenant->merchant_code],
                [
                    'tenant_id' => $tenant->id,
                    'name' => '演示 ERP',
                    'app_secret' => Hash::make('secret'),
                    'signing_secret' => 'secret',
                    'permissions' => [
                        ApiPermission::ProductQuery->value,
                        ApiPermission::OrderManage->value,
                        ApiPermission::DashboardRead->value,
                        ApiPermission::BillQuery->value,
                    ],
                    'status' => 'enabled',
                ],
            );

            TenantBill::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'billing_period' => now()->subMonthNoOverflow()->format('Y-m'),
                ],
                [
                    'transaction_total' => 598.00,
                    'commission_amount' => 11.96,
                    'api_usage_fee' => 0,
                    'api_overage_fee' => 0,
                    'total_receivable' => 11.96,
                    'status' => BillStatus::PendingSettlement,
                ],
            );
        }
    }
}
