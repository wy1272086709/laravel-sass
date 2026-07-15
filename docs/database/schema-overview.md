# 数据库结构 / 迁移 / Model / Factory / Seeder

## 迁移文件清单

按依赖顺序排列，文件名遵循 Laravel 时间戳命名。

```
database/migrations/
├── 0001_01_01_000000_create_packages_table.php
├── 0001_01_01_000001_create_platform_roles_table.php
├── 0001_01_01_000002_create_platform_permissions_table.php
├── 0001_01_01_000003_create_platform_role_permission_table.php
├── 0001_01_01_000004_create_platform_users_table.php
├── 0001_01_01_000005_create_tenants_table.php
├── 0001_01_01_000006_create_merchant_users_table.php
├── 0001_01_01_000007_create_tenant_settings_table.php
├── 0001_01_01_000008_create_products_table.php
├── 0001_01_01_000009_create_product_skus_table.php
├── 0001_01_01_000010_create_orders_table.php
├── 0001_01_01_000011_create_order_items_table.php
├── 0001_01_01_000012_create_coupons_table.php
├── 0001_01_01_000013_create_api_keys_table.php
├── 0001_01_01_000014_create_api_request_logs_table.php
├── 0001_01_01_000015_create_api_usage_daily_table.php
├── 0001_01_01_000016_create_tenant_bills_table.php
├── 0001_01_01_000017_create_reconciliation_discrepancies_table.php
├── 0001_01_01_000018_create_risk_rules_table.php
├── 0001_01_01_000019_create_risk_alerts_table.php
├── 0001_01_01_000020_create_queue_job_logs_table.php
├── 0001_01_01_000021_create_package_change_logs_table.php
├── 0001_01_01_000022_create_login_logs_table.php
├── 0001_01_01_000023_create_impersonation_logs_table.php
├── 0001_01_01_000024_create_personal_access_tokens_table.php
└── 0001_01_01_000025_create_failed_jobs_table.php
```

---

## 核心迁移 DDL 参考

### packages

```php
Schema::create('packages', function (Blueprint $table) {
    $table->id();
    $table->string('tier');                          // PackageTier enum
    $table->string('name');
    $table->decimal('price_monthly', 10, 2);
    $table->unsignedInteger('api_quota_daily');
    $table->unsignedInteger('merchant_limit')->default(1);
    $table->json('features')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### tenants

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('merchant_code')->unique();         // MHT-89201
    $table->string('name');
    $table->string('contact_name');
    $table->string('contact_phone');
    $table->foreignId('package_id')->constrained();
    $table->string('status')->default('enabled');    // TenantStatus enum
    $table->decimal('commission_rate', 5, 4)->default(0.0200);
    $table->timestamp('joined_at');
    $table->timestamps();
    $table->softDeletes();
});
```

### products (SPU)

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('product_code');                  // G-10002
    $table->string('name');
    $table->string('cover_image')->nullable();
    $table->decimal('price', 10, 2);
    $table->unsignedInteger('stock')->default(0);
    $table->unsignedInteger('sales_count')->default(0);
    $table->json('specs')->nullable();               // {"颜色":"象牙白","尺码":"L"}
    $table->string('status')->default('listed');     // ProductStatus enum
    $table->timestamps();
    $table->softDeletes();
    $table->index(['tenant_id', 'status']);
    $table->unique(['tenant_id', 'product_code']);
});
```

### product_skus

商户后台和开放 API 均支持维护多 SKU；商品主表的 `price` 为最低 SKU 价格，`stock` 为 SKU 库存汇总。

```php
Schema::create('product_skus', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('sku_code');
    $table->json('specs');
    $table->decimal('price', 10, 2);
    $table->unsignedInteger('stock')->default(0);
    $table->timestamps();
    $table->unique(['tenant_id', 'sku_code']);
});
```

### orders

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('order_no')->unique();            // ORD202607020001
    $table->string('buyer_name');
    $table->string('buyer_phone');
    $table->decimal('total_amount', 12, 2);
    $table->string('status')->default('pending_payment');
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('shipped_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->string('cancel_reason')->nullable();
    $table->timestamps();
    $table->index(['tenant_id', 'status']);
    $table->index(['tenant_id', 'created_at']);
});
```

### order_items

```php
Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('sku_id')->nullable()->constrained('product_skus');
    $table->string('product_name');
    $table->json('spec_snapshot');                   // 下单时规格快照
    $table->decimal('unit_price', 10, 2);
    $table->unsignedInteger('quantity');
    $table->timestamps();
});
```

### tenant_bills

```php
Schema::create('tenant_bills', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('billing_period', 7);             // 2026-06
    $table->decimal('transaction_total', 14, 2)->default(0);
    $table->decimal('commission_amount', 12, 2)->default(0);
    $table->decimal('api_usage_fee', 10, 2)->default(0);
    $table->decimal('api_overage_fee', 10, 2)->default(0);
    $table->decimal('total_receivable', 12, 2)->default(0);
    $table->decimal('merchant_reported_amount', 12, 2)->nullable();
    $table->decimal('difference_amount', 12, 2)->nullable();
    $table->string('status')->default('pending_settlement');
    // ── C 字段预留 ──
    $table->string('payment_channel')->nullable();
    $table->string('external_transaction_no')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->json('payment_meta')->nullable();
    $table->timestamps();
    $table->unique(['tenant_id', 'billing_period']);
});
```

### api_keys

```php
Schema::create('api_keys', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('app_key')->unique();             // AK_42819203
    $table->string('app_secret');                    // HASH 存储
    $table->json('permissions');                     // ApiPermission[]
    $table->string('status')->default('enabled');
    $table->timestamp('last_used_at')->nullable();
    $table->timestamps();
});
```

---

## Model 目录结构

```
app/Models/
├── Platform/
│   ├── PlatformUser.php          extends Authenticatable, Guard: platform
│   ├── PlatformRole.php
│   └── PlatformPermission.php
├── Tenant/
│   ├── Tenant.php                无 TenantScope（本身即租户实体）
│   ├── MerchantUser.php          extends Authenticatable, Guard: merchant
│   └── TenantSetting.php
├── Product/
│   ├── Product.php               TenantScope
│   └── ProductSku.php            TenantScope
├── Order/
│   ├── Order.php                 TenantScope
│   └── OrderItem.php             TenantScope
├── Marketing/
│   └── Coupon.php                TenantScope
├── Api/
│   ├── ApiKey.php                TenantScope
│   ├── ApiRequestLog.php         TenantScope
│   └── ApiUsageDaily.php         TenantScope
├── Billing/
│   ├── TenantBill.php            TenantScope
│   └── ReconciliationDiscrepancy.php
├── Risk/
│   ├── RiskRule.php
│   └── RiskAlert.php
├── Package.php
├── PackageChangeLog.php
├── QueueJobLog.php
├── LoginLog.php
└── ImpersonationLog.php

app/Models/Concerns/
└── BelongsToTenant.php           Trait: bootBelongsToTenant() 自动 Scope + 创建注入
```

### Model 示例：Product

```php
class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'product_code', 'name', 'cover_image', 'price',
        'stock', 'sales_count', 'specs', 'status',
    ];

    protected function casts(): array
    {
        return [
            'specs'  => 'array',
            'price'  => 'decimal:2',
            'status' => ProductStatus::class,
        ];
    }

    public function skus(): HasMany
    {
        return $this->hasMany(ProductSku::class);
    }
}
```

### BelongsToTenant Trait

```php
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->tenant_id) {
                $model->tenant_id = app(TenantContext::class)->tenantId;
            }
        });
    }
}
```

---

## Factory 目录结构

```
database/factories/
├── Platform/
│   ├── PackageFactory.php
│   └── PlatformUserFactory.php
├── Tenant/
│   ├── TenantFactory.php
│   └── MerchantUserFactory.php
├── Product/
│   └── ProductFactory.php
├── Order/
│   ├── OrderFactory.php
│   └── OrderItemFactory.php
├── Marketing/
│   └── CouponFactory.php
├── Api/
│   ├── ApiKeyFactory.php
│   └── ApiRequestLogFactory.php
├── Billing/
│   ├── ReconciliationDiscrepancyFactory.php
│   └── TenantBillFactory.php
├── Risk/
│   ├── RiskAlertFactory.php
│   └── RiskRuleFactory.php
└── System/
    └── QueueJobLogFactory.php
```

### Factory 示例：TenantFactory

```php
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_code'  => 'MHT-' . fake()->unique()->numerify('#####'),
            'name'           => fake()->company() . '专营店',
            'contact_name'   => fake()->name(),
            'contact_phone'  => '138' . fake()->numerify('####') . fake()->numerify('####'),
            'package_id'     => Package::factory(),
            'status'         => TenantStatus::Enabled,
            'commission_rate'=> 0.02,
            'joined_at'      => fake()->dateTimeBetween('-2 years'),
        ];
    }
}
```

---

## Seeder 目录结构

```
database/seeders/
├── DatabaseSeeder.php            主入口，按序调用
├── PackageSeeder.php             三档套餐（基础/专业/企业）
├── PlatformAdminSeeder.php       平台管理员 + 角色权限
└── TenantSeeder.php              3 个演示商户 + 商品/订单/优惠券/API Key/月账单
```

### DatabaseSeeder 调用顺序

```php
public function run(): void
{
    $this->call([
        PackageSeeder::class,
        PlatformAdminSeeder::class,
        TenantSeeder::class,
    ]);
}
```

---

## 索引策略汇总

| 表 | 索引 | 类型 |
|----|------|------|
| `tenants` | `merchant_code` | UNIQUE |
| `products` | `(tenant_id, status)` | INDEX |
| `products` | `(tenant_id, product_code)` | UNIQUE |
| `orders` | `(tenant_id, status)` | INDEX |
| `orders` | `(tenant_id, created_at)` | INDEX |
| `orders` | `order_no` | UNIQUE |
| `api_keys` | `app_key` | UNIQUE |
| `api_request_logs` | `requested_at` | INDEX |
| `api_usage_daily` | `(tenant_id, usage_date)` | UNIQUE |
| `tenant_bills` | `(tenant_id, billing_period)` | UNIQUE |
| `risk_alerts` | `(status, risk_level)` | INDEX |
