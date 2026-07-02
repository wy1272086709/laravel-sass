# 多商户 SaaS 电商开放中台 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 交付完整的多商户 SaaS 电商开放中台 MVP——双 Filament 后台、4 个 Vue3 面板、开放 API 网关、Redis 分布式层、Octane 多租户隔离。

**Architecture:** 模块化单体 Laravel 11 应用；`readonly TenantContext` + `TenantScope` 共享库多租户；双 Guard（platform/merchant）+ Impersonation；Redis Lua 限流/锁/队列；Filament v3 承载 14 个纯后台页面 + 4 个 Vue3 Echarts 嵌入面板。

**Tech Stack:** PHP 8.3, Laravel 11, Filament v3, Vue 3, Echarts 5, Laravel Octane (Swoole), Redis 7, MySQL 8, Sanctum, Pest PHP

## Global Constraints

- PHP 8.3：所有 Enum 使用 backed enum；`TenantContext` 必须为 `readonly`
- Laravel 11 + Filament v3 原生组件为主
- 仅 4 个 Vue3 嵌入面板：平台仪表盘 / API 监控 / 队列中心 / 风控对账
- Laravel Octane Swoole + `OctaneTenantCleanupMiddleware` 防租户串号
- Redis：Lua 原子锁、滑动窗口限流、延迟队列、死信队列
- 共享库多租户：全局 `tenant_id` Scope + `SqlTenantGuard` 拦截裸 SQL
- 商户独立收款：账单仅状态流转（A），`payment_channel` 等字段预留（C）
- 对外 API 统一前缀 `/api/v1`；双 Token（后台 Session / 第三方 AccessToken）完全隔离
- 账号体系：双表独立（`platform_users` / `merchant_users`）+ Impersonation
- API 配额：基础版硬阻断；专业/企业版软告警+超额计费；150% 全局硬阻断
- 商品：本期单 SKU Filament 交互；`product_skus` 表预留
- 风控：5 条轻量规则引擎；对账差异月结半自动生成

**Spec:** `docs/superpowers/specs/2026-07-02-saas-ecommerce-platform-design.md`
**Schema:** `docs/database/schema-overview.md`
**OpenAPI:** `docs/api/openapi.yaml`

---

## File Structure Overview

```
app/
├── Domain/
│   ├── Enums/                    # 16 backed enums
│   ├── Tenant/
│   │   ├── TenantContext.php
│   │   ├── TenantScope.php
│   │   └── Scopes/
│   ├── Order/OrderStateMachine.php
│   ├── Billing/BillSettlementService.php
│   ├── Risk/RuleEngine.php
│   └── Api/AccessTokenService.php
├── Application/
│   ├── Platform/
│   ├── Merchant/
│   └── Api/
├── Infrastructure/
│   ├── Redis/
│   │   ├── LuaDistributedLock.php
│   │   ├── SlidingWindowRateLimiter.php
│   │   ├── DelayQueue.php
│   │   ├── DeadLetterQueue.php
│   │   └── ApiDailyCounter.php
│   └── Octane/
│       ├── SqlTenantGuard.php
│       └── OctaneTenantCleanupMiddleware.php
├── Models/                       # 22 Eloquent models + BelongsToTenant trait
├── Filament/
│   ├── Platform/                 # PlatformPanelProvider + Resources + Pages
│   └── Merchant/                 # MerchantPanelProvider + Resources + Pages
├── Http/
│   ├── Middleware/
│   ├── Controllers/Api/V1/
│   ├── Controllers/Internal/
│   └── Controllers/ImpersonationController.php
└── Jobs/                         # 8 queue jobs
resources/js/panels/              # 4 Vue3 components
database/migrations/              # 26 migrations
tests/
├── Unit/Domain/
├── Unit/Infrastructure/
└── Feature/
```

---

## Phase 1: 项目脚手架

### Task 1: Laravel 11 项目初始化

**Files:**
- Create: Laravel 11 project in repo root (alongside `docs/`)
- Create: `composer.json` dependencies
- Create: `.env.example`
- Modify: `README.md` (add dev commands)

**Interfaces:**
- Produces: runnable `php artisan serve` baseline

- [ ] **Step 1: 创建 Laravel 11 项目**

```bash
cd /Users/mac/laravelProj
composer create-project laravel/laravel:^11.0 temp-app --prefer-dist
mv temp-app/* temp-app/.[!.]* . 2>/dev/null || true
rm -rf temp-app
composer require filament/filament:"^3.3" laravel/octane predis/predis laravel/sanctum
php artisan octane:install --server=swoole
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

- [ ] **Step 2: 安装前端依赖（Vue3 + Echarts）**

```bash
npm install vue@3 echarts vue-echarts
npm install -D @vitejs/plugin-vue
```

- [ ] **Step 3: 配置 `vite.config.js` 多入口**

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/panels/platform-dashboard.js',
                'resources/js/panels/api-monitoring.js',
                'resources/js/panels/queue-ops.js',
                'resources/js/panels/risk-reconciliation.js',
            ],
            refresh: true,
        }),
        vue(),
    ],
});
```

- [ ] **Step 4: 验证启动**

```bash
php artisan --version   # Laravel 11.x
php artisan test        # PASS (default tests)
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel 11 with Filament, Octane, Vue3"
```

---

### Task 2: Pest 测试环境与目录结构

**Files:**
- Create: `tests/Unit/Domain/.gitkeep` → replace with real tests in later tasks
- Create: `tests/Feature/Api/.gitkeep`
- Modify: `phpunit.xml` (SQLite in-memory for tests)

**Interfaces:**
- Produces: `php artisan test` green baseline with custom dirs

- [ ] **Step 1: 安装 Pest**

```bash
composer require pestphp/pest --dev --with-all-dependencies
php artisan pest:install
```

- [ ] **Step 2: 配置测试数据库 `phpunit.xml`**

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="CACHE_STORE" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
```

- [ ] **Step 3: 创建领域目录**

```bash
mkdir -p app/Domain/{Enums,Tenant,Order,Billing,Risk,Api}
mkdir -p app/Application/{Platform,Merchant,Api}
mkdir -p app/Infrastructure/{Redis,Octane}
mkdir -p app/Models/{Platform,Tenant,Product,Order,Marketing,Api,Billing,Risk}
mkdir -p app/Models/Concerns
mkdir -p resources/js/panels
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore: setup Pest and domain directory structure"
```

---

## Phase 2: 领域 Enum 与 TenantContext

### Task 3: Backed Enum 契约

**Files:**
- Create: `app/Domain/Enums/PackageTier.php`
- Create: `app/Domain/Enums/TenantStatus.php`
- Create: `app/Domain/Enums/ProductStatus.php`
- Create: `app/Domain/Enums/OrderStatus.php`
- Create: `app/Domain/Enums/CouponType.php`
- Create: `app/Domain/Enums/CouponStatus.php`
- Create: `app/Domain/Enums/ApiKeyStatus.php`
- Create: `app/Domain/Enums/ApiPermission.php`
- Create: `app/Domain/Enums/BillStatus.php`
- Create: `app/Domain/Enums/RiskLevel.php`
- Create: `app/Domain/Enums/RiskAlertType.php`
- Create: `app/Domain/Enums/RiskAlertStatus.php`
- Create: `app/Domain/Enums/ReconciliationStatus.php`
- Create: `app/Domain/Enums/PackageChangeType.php`
- Create: `app/Domain/Enums/QueueJobStatus.php`
- Create: `app/Domain/Enums/LoginResult.php`
- Test: `tests/Unit/Domain/Enums/PackageTierTest.php`

**Interfaces:**
- Produces: `App\Domain\Enums\*` namespace, all enums importable

- [ ] **Step 1: 写失败测试**

```php
// tests/Unit/Domain/Enums/PackageTierTest.php
use App\Domain\Enums\PackageTier;

it('has three tiers matching spec', function () {
    expect(PackageTier::cases())->toHaveCount(3);
    expect(PackageTier::Basic->value)->toBe('basic');
    expect(PackageTier::Professional->value)->toBe('professional');
    expect(PackageTier::Enterprise->value)->toBe('enterprise');
});

it('basic tier hard blocks on overage', function () {
    expect(PackageTier::Basic->hardBlockOnOverage())->toBeTrue();
    expect(PackageTier::Professional->hardBlockOnOverage())->toBeFalse();
});
```

- [ ] **Step 2: 运行确认失败**

```bash
php artisan test tests/Unit/Domain/Enums/PackageTierTest.php
# Expected: FAIL class not found
```

- [ ] **Step 3: 实现所有 Enum（示例 PackageTier，其余同理）**

```php
// app/Domain/Enums/PackageTier.php
namespace App\Domain\Enums;

enum PackageTier: string
{
    case Basic = 'basic';
    case Professional = 'professional';
    case Enterprise = 'enterprise';

    public function hardBlockOnOverage(): bool
    {
        return $this === self::Basic;
    }

    public function allowsOverageBilling(): bool
    {
        return $this !== self::Basic;
    }

    public function label(): string
    {
        return match ($this) {
            self::Basic => '基础版',
            self::Professional => '专业版',
            self::Enterprise => '企业版',
        };
    }
}
```

```php
// app/Domain/Enums/OrderStatus.php — 含状态机合法转移
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case RefundRequested = 'refund_requested';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::PendingPayment => in_array($target, [self::Paid, self::Cancelled]),
            self::Paid => in_array($target, [self::Shipped, self::RefundRequested, self::Cancelled]),
            self::Shipped => $target === self::Completed,
            self::RefundRequested => in_array($target, [self::Completed, self::Cancelled]),
            default => false,
        };
    }
}
```

- [ ] **Step 4: 运行测试通过**

```bash
php artisan test tests/Unit/Domain/Enums/
# Expected: PASS
```

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Enums tests/Unit/Domain/Enums
git commit -m "feat: add 16 backed enums with domain methods"
```

---

### Task 4: TenantContext + TenantScope

**Files:**
- Create: `app/Domain/Tenant/TenantContext.php`
- Create: `app/Domain/Tenant/TenantScope.php`
- Create: `app/Models/Concerns/BelongsToTenant.php`
- Create: `app/Providers/TenantServiceProvider.php`
- Test: `tests/Unit/Domain/Tenant/TenantScopeTest.php`

**Interfaces:**
- Produces: `TenantContext` (readonly), `TenantScope` (GlobalScope), `BelongsToTenant` trait
- Consumes: `App\Domain\Enums\PackageTier`

- [ ] **Step 1: 写失败测试**

```php
// tests/Unit/Domain/Tenant/TenantScopeTest.php
use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Domain\Tenant\TenantScope;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate');
});

it('filters queries by tenant_id from context', function () {
    app()->instance(TenantContext::class, new TenantContext(1, null, PackageTier::Basic));

    Product::withoutEvents(fn () => Product::create([
        'tenant_id' => 1, 'product_code' => 'G-001', 'name' => 'A',
        'price' => 10, 'stock' => 5, 'status' => 'listed',
    ]));
    Product::withoutEvents(fn () => Product::create([
        'tenant_id' => 2, 'product_code' => 'G-002', 'name' => 'B',
        'price' => 20, 'stock' => 3, 'status' => 'listed',
    ]));

    expect(Product::count())->toBe(1);
});
```

- [ ] **Step 2: 实现 TenantContext**

```php
// app/Domain/Tenant/TenantContext.php
namespace App\Domain\Tenant;

use App\Domain\Enums\PackageTier;

readonly class TenantContext
{
    public function __construct(
        public ?int $tenantId,
        public ?int $impersonatorId,
        public PackageTier $tier,
    ) {}

    public function isPlatformView(): bool
    {
        return $this->tenantId === null;
    }

    public function isImpersonating(): bool
    {
        return $this->impersonatorId !== null;
    }
}
```

- [ ] **Step 3: 实现 TenantScope + BelongsToTenant**

```php
// app/Domain/Tenant/TenantScope.php
namespace App\Domain\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);
        if ($context->tenantId !== null) {
            $builder->where($model->getTable().'.tenant_id', $context->tenantId);
        }
    }
}
```

```php
// app/Models/Concerns/BelongsToTenant.php
namespace App\Models\Concerns;

use App\Domain\Tenant\TenantContext;
use App\Domain\Tenant\TenantScope;

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

- [ ] **Step 4: 注册 TenantServiceProvider 默认空上下文**

```php
// app/Providers/TenantServiceProvider.php
public function register(): void
{
    $this->app->singleton(TenantContext::class, fn () => new TenantContext(
        tenantId: null,
        impersonatorId: null,
        tier: PackageTier::Basic,
    ));
}
```

- [ ] **Step 5: 测试通过 + Commit**

```bash
php artisan test tests/Unit/Domain/Tenant/
git commit -am "feat: add readonly TenantContext and TenantScope"
```

---

## Phase 3: 数据库迁移与 Model

### Task 5: 平台域迁移（packages, platform_users, roles）

**Files:**
- Create: `database/migrations/0001_01_01_000000_create_packages_table.php`
- Create: `database/migrations/0001_01_01_000001_create_platform_roles_table.php`
- Create: `database/migrations/0001_01_01_000002_create_platform_permissions_table.php`
- Create: `database/migrations/0001_01_01_000003_create_platform_role_permission_table.php`
- Create: `database/migrations/0001_01_01_000004_create_platform_users_table.php`
- Create: `app/Models/Package.php`
- Create: `app/Models/Platform/PlatformUser.php`
- Create: `app/Models/Platform/PlatformRole.php`
- Create: `database/factories/PackageFactory.php`
- Test: `tests/Feature/Database/PlatformMigrationsTest.php`

**Interfaces:**
- Produces: `Package`, `PlatformUser`, `PlatformRole` models; migrations runnable

- [ ] **Step 1: 写迁移（参照 `docs/database/schema-overview.md` DDL）**

- [ ] **Step 2: 写失败测试**

```php
it('migrates platform tables', function () {
    $this->artisan('migrate');
    expect(\Schema::hasTable('packages'))->toBeTrue();
    expect(\Schema::hasTable('platform_users'))->toBeTrue();
});
```

- [ ] **Step 3: 实现 Model + Factory**

```php
// app/Models/Platform/PlatformUser.php
class PlatformUser extends Authenticatable
{
    use HasApiTokens, HasFactory;
    protected $guard = 'platform';
    protected $fillable = ['name','email','password','phone','department','role_id'];
    protected $hidden = ['password'];
    protected function casts(): array {
        return ['password' => 'hashed'];
    }
}
```

- [ ] **Step 4: 测试通过**

```bash
php artisan migrate:fresh
php artisan test tests/Feature/Database/PlatformMigrationsTest.php
```

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: add platform domain migrations and models"
```

---

### Task 6: 租户域迁移（tenants, products, orders, bills, api_keys）

**Files:**
- Create: migrations `000005` through `000023` per `docs/database/schema-overview.md`
- Create: all tenant-scoped Models with `BelongsToTenant`
- Create: corresponding Factories
- Test: `tests/Feature/Database/TenantMigrationsTest.php`

**Interfaces:**
- Produces: 28 tables migrated; `Tenant`, `Product`, `Order`, `TenantBill`, `ApiKey` models
- Consumes: `BelongsToTenant`, all Enums

- [ ] **Step 1: 批量创建迁移文件（按 schema-overview 顺序）**

- [ ] **Step 2: 写测试验证 tenant_bills 预留字段**

```php
it('tenant_bills has payment reservation fields', function () {
    $this->artisan('migrate');
    expect(\Schema::hasColumns('tenant_bills', [
        'payment_channel', 'external_transaction_no', 'paid_at', 'payment_meta',
    ]))->toBeTrue();
});
```

- [ ] **Step 3: 实现核心 Model**

```php
// app/Models/Billing/TenantBill.php
class TenantBill extends Model
{
    use BelongsToTenant, HasFactory;
    protected $fillable = [
        'tenant_id','billing_period','transaction_total','commission_amount',
        'api_usage_fee','api_overage_fee','total_receivable',
        'merchant_reported_amount','difference_amount','status',
        'payment_channel','external_transaction_no','paid_at','payment_meta',
    ];
    protected function casts(): array {
        return [
            'status' => BillStatus::class,
            'payment_meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 4: migrate:fresh 通过**

```bash
php artisan migrate:fresh
php artisan test tests/Feature/Database/
```

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: add tenant domain migrations, models, factories"
```

---

### Task 7: OrderStateMachine 领域服务

**Files:**
- Create: `app/Domain/Order/OrderStateMachine.php`
- Test: `tests/Unit/Domain/Order/OrderStateMachineTest.php`

**Interfaces:**
- Consumes: `OrderStatus` enum
- Produces: `OrderStateMachine::transition(Order $order, OrderStatus $target): void`

- [ ] **Step 1: 写失败测试**

```php
it('transitions pending_payment to paid', function () {
    $order = Order::factory()->create(['status' => OrderStatus::PendingPayment]);
    app(OrderStateMachine::class)->transition($order, OrderStatus::Paid);
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
    expect($order->fresh()->paid_at)->not->toBeNull();
});

it('rejects illegal transition', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Completed]);
    app(OrderStateMachine::class)->transition($order, OrderStatus::Paid);
})->throws(DomainException::class);
```

- [ ] **Step 2: 实现**

```php
// app/Domain/Order/OrderStateMachine.php
class OrderStateMachine
{
    public function transition(Order $order, OrderStatus $target): void
    {
        if (! $order->status->canTransitionTo($target)) {
            throw new DomainException("Cannot transition from {$order->status->value} to {$target->value}");
        }
        $order->status = $target;
        match ($target) {
            OrderStatus::Paid => $order->paid_at = now(),
            OrderStatus::Shipped => $order->shipped_at = now(),
            OrderStatus::Cancelled => $order->cancelled_at = now(),
            default => null,
        };
        $order->save();
    }
}
```

- [ ] **Step 3: 测试通过 + Commit**

```bash
php artisan test tests/Unit/Domain/Order/
git commit -am "feat: add OrderStateMachine with guarded transitions"
```

---

## Phase 4: Redis 基础设施

### Task 8: SlidingWindowRateLimiter + ApiDailyCounter

**Files:**
- Create: `app/Infrastructure/Redis/SlidingWindowRateLimiter.php`
- Create: `app/Infrastructure/Redis/ApiDailyCounter.php`
- Test: `tests/Unit/Infrastructure/Redis/SlidingWindowRateLimiterTest.php`

**Interfaces:**
- Produces: `SlidingWindowRateLimiter::attempt(string $key, int $limit, int $windowSeconds): bool`
- Produces: `ApiDailyCounter::increment(int $tenantId): int`

- [ ] **Step 1: 写失败测试（使用 Redis fake 或 predis mock）**

```php
it('blocks when daily quota exceeded', function () {
    $limiter = app(SlidingWindowRateLimiter::class);
    $key = 'api:daily:1:2026-07-02';
    for ($i = 0; $i < 100; $i++) { $limiter->hit($key, 86400); }
    expect($limiter->tooManyAttempts($key, 100))->toBeTrue();
});
```

- [ ] **Step 2: 实现 Lua 滑动窗口**

```php
// app/Infrastructure/Redis/SlidingWindowRateLimiter.php
class SlidingWindowRateLimiter
{
    private const LUA = <<<'LUA'
        local key = KEYS[1]
        local now = tonumber(ARGV[1])
        local window = tonumber(ARGV[2])
        local limit = tonumber(ARGV[3])
        redis.call('ZREMRANGEBYSCORE', key, 0, now - window)
        local count = redis.call('ZCARD', key)
        if count < limit then
            redis.call('ZADD', key, now, now .. ':' .. math.random())
            redis.call('EXPIRE', key, window)
            return 0
        end
        return 1
    LUA;

    public function tooManyAttempts(string $key, int $limit, int $window = 86400): bool
    {
        return (bool) Redis::eval(self::LUA, 1, $key, microtime(true), $window, $limit);
    }
}
```

- [ ] **Step 3: 实现分级限流策略服务**

```php
// app/Application/Api/QuotaPolicyService.php
class QuotaPolicyService
{
    public function shouldBlock(PackageTier $tier, int $used, int $quota): bool
    {
        if ($tier->hardBlockOnOverage() && $used >= $quota) return true;
        if ($used >= (int)($quota * 1.5)) return true; // 150% 全局硬阻断
        return false;
    }
}
```

- [ ] **Step 4: 测试通过 + Commit**

```bash
php artisan test tests/Unit/Infrastructure/Redis/
git commit -am "feat: add Redis sliding window rate limiter and quota policy"
```

---

### Task 9: LuaDistributedLock + DelayQueue + DeadLetterQueue

**Files:**
- Create: `app/Infrastructure/Redis/LuaDistributedLock.php`
- Create: `app/Infrastructure/Redis/DelayQueue.php`
- Create: `app/Infrastructure/Redis/DeadLetterQueue.php`
- Test: `tests/Unit/Infrastructure/Redis/LuaDistributedLockTest.php`

**Interfaces:**
- Produces: `LuaDistributedLock::acquire(string $key, int $ttlMs): ?string` (token)
- Produces: `DelayQueue::push(string $queue, array $payload, int $delaySeconds): void`
- Produces: `DeadLetterQueue::move(string $jobUuid, string $reason): void`

- [ ] **Step 1-4: TDD 实现三个 Redis 组件（参照 SDD §6）**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: add Redis distributed lock, delay queue, dead letter queue"
```

---

## Phase 5: Octane 中间件链

### Task 10: ResolveTenantContext + OctaneTenantCleanupMiddleware

**Files:**
- Create: `app/Http/Middleware/ResolveTenantContext.php`
- Create: `app/Infrastructure/Octane/OctaneTenantCleanupMiddleware.php`
- Create: `app/Infrastructure/Octane/SqlTenantGuard.php`
- Modify: `bootstrap/app.php` (register middleware)
- Test: `tests/Feature/Middleware/TenantIsolationTest.php`

**Interfaces:**
- Consumes: `TenantContext`, Guards
- Produces: middleware registered on `web` + `api` groups

- [ ] **Step 1: 写失败测试 — 连续请求不串租户**

```php
it('cleans tenant context after request', function () {
    app()->instance(TenantContext::class, new TenantContext(99, null, PackageTier::Basic));
    $this->get('/health');
    expect(app(TenantContext::class)->tenantId)->toBeNull();
});
```

- [ ] **Step 2: 实现 ResolveTenantContext**

```php
// app/Http/Middleware/ResolveTenantContext.php
public function handle(Request $request, Closure $next): Response
{
    $tenantId = null;
    $impersonatorId = null;
    $tier = PackageTier::Basic;

    if ($user = auth('merchant')->user()) {
        $tenantId = $user->tenant_id;
        $tier = $user->tenant->package->tier;
        $impersonatorId = session('impersonated_by');
    }

    app()->instance(TenantContext::class, new TenantContext($tenantId, $impersonatorId, $tier));

    return $next($request);
}
```

- [ ] **Step 3: 实现 OctaneTenantCleanupMiddleware**

```php
public function handle(Request $request, Closure $next): Response
{
    try {
        return $next($request);
    } finally {
        app()->forgetInstance(TenantContext::class);
        app()->instance(TenantContext::class, new TenantContext(null, null, PackageTier::Basic));
    }
}
```

- [ ] **Step 4: 实现 SqlTenantGuard（DB::listen 检测）**

- [ ] **Step 5: 注册到 `bootstrap/app.php` + 测试通过 + Commit**

```bash
git commit -am "feat: add Octane tenant middleware chain and SqlTenantGuard"
```

---

### Task 11: ApiRateLimitMiddleware

**Files:**
- Create: `app/Http/Middleware/ApiRateLimitMiddleware.php`
- Create: `app/Http/Middleware/ApiAuthMiddleware.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/RateLimitTest.php`

**Interfaces:**
- Consumes: `SlidingWindowRateLimiter`, `QuotaPolicyService`, `TenantContext`
- Produces: 429 JSON response per OpenAPI spec

- [ ] **Step 1: 写失败测试**

```php
it('returns 429 when basic tier exceeds quota', function () {
    $tenant = Tenant::factory()->basic()->create();
    $key = ApiKey::factory()->for($tenant)->create();
    // seed counter at quota
    Redis::set("api:daily:{$tenant->id}:".now()->toDateString(), 10000);
    $token = app(AccessTokenService::class)->issue($key);

    $response = $this->withToken($token)->getJson('/api/v1/products');
    $response->assertStatus(429)->assertJsonPath('code', 42901);
});
```

- [ ] **Step 2-4: 实现中间件 + 测试通过**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: add API rate limit middleware with tiered quota policy"
```

---

## Phase 6: 双 Guard 认证与 Impersonation

### Task 12: 双 Guard 配置 + 共用登录页

**Files:**
- Modify: `config/auth.php`
- Create: `app/Filament/Pages/Auth/UnifiedLogin.php`
- Modify: `app/Providers/Filament/PlatformPanelProvider.php`
- Modify: `app/Providers/Filament/MerchantPanelProvider.php`
- Test: `tests/Feature/Auth/DualGuardLoginTest.php`

**Interfaces:**
- Produces: `platform` and `merchant` guards; unified login with Tab switch

- [ ] **Step 1: 配置双 Guard `config/auth.php`**

```php
'guards' => [
    'platform' => ['driver' => 'session', 'provider' => 'platform_users'],
    'merchant' => ['driver' => 'session', 'provider' => 'merchant_users'],
],
'providers' => [
    'platform_users' => ['driver' => 'eloquent', 'model' => PlatformUser::class],
    'merchant_users' => ['driver' => 'eloquent', 'model' => MerchantUser::class],
],
```

- [ ] **Step 2: 创建双 PanelProvider**

```php
// PlatformPanelProvider
return $panel->id('platform')->path('platform')->authGuard('platform')
    ->login(UnifiedLogin::class)->brandName('SaaS 电商中台');
// MerchantPanelProvider
return $panel->id('merchant')->path('merchant')->authGuard('merchant')
    ->login(UnifiedLogin::class)->brandName('商户管理后台');
```

- [ ] **Step 3: UnifiedLogin Tab 切换（参照截图 login.png）**

- [ ] **Step 4: 测试双 Guard 登录隔离**

```bash
php artisan test tests/Feature/Auth/
git commit -am "feat: add dual guard auth with unified login page"
```

---

### Task 13: Impersonation 流程

**Files:**
- Create: `app/Http/Controllers/ImpersonationController.php`
- Create: `app/Models/ImpersonationLog.php`
- Modify: Platform/Merchant Panel navigation (切换系统)
- Test: `tests/Feature/Auth/ImpersonationTest.php`

**Interfaces:**
- Produces: `ImpersonationController::start(int $tenantId)`, `::stop()`
- Produces: `impersonation_logs` audit records

- [ ] **Step 1: 写失败测试**

```php
it('platform admin can impersonate merchant', function () {
    $admin = PlatformUser::factory()->create();
    $tenant = Tenant::factory()->create();
    MerchantUser::factory()->for($tenant)->create();

    $this->actingAs($admin, 'platform')
        ->post("/platform/impersonate/{$tenant->id}")
        ->assertRedirect('/merchant');

    expect(auth('merchant')->check())->toBeTrue();
    expect(session('impersonated_by'))->toBe($admin->id);
});
```

- [ ] **Step 2-4: 实现 start/stop + 审计日志 + Sidebar 入口**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: add impersonation with audit logging"
```

---

## Phase 7: 平台 Filament 后台

### Task 14: TenantResource（商户管理）

**Files:**
- Create: `app/Filament/Platform/Resources/TenantResource.php`
- Create: `app/Filament/Platform/Resources/TenantResource/Pages/`
- Test: `tests/Feature/Filament/Platform/TenantResourceTest.php`

**Interfaces:**
- Produces: CRUD for tenants with StatsOverview, bulk enable/disable

- [ ] **Step 1: 生成 Resource**

```bash
php artisan make:filament-resource Tenant --panel=platform --generate
```

- [ ] **Step 2: 配置 Table 列（参照截图：商户ID/名称/联系人/套餐/状态/入驻时间）**

- [ ] **Step 3: Livewire 测试**

```php
it('lists tenants for platform admin', function () {
    Tenant::factory()->count(3)->create();
    livewire(ListTenants::class)->assertCanSeeTableRecords(Tenant::all());
});
```

- [ ] **Step 4: Commit**

```bash
git commit -am "feat: add platform TenantResource"
```

---

### Task 15: PackageResource + PlatformRoleResource

**Files:**
- Create: `app/Filament/Platform/Resources/PackageResource.php`
- Create: `app/Filament/Platform/Resources/PlatformRoleResource.php`
- Create: `app/Filament/Platform/Pages/PlatformProfilePage.php`

- [ ] **Step 1-3: 实现套餐卡片 Grid + 变更历史 Relation + 角色权限 Checkbox 树**

- [ ] **Step 4: Commit**

```bash
git commit -am "feat: add package config and role permission resources"
```

---

## Phase 8: 商户 Filament 后台

### Task 16: ProductResource + OrderResource

**Files:**
- Create: `app/Filament/Merchant/Resources/ProductResource.php`
- Create: `app/Filament/Merchant/Resources/OrderResource.php`
- Test: `tests/Feature/Filament/Merchant/TenantIsolationResourceTest.php`

- [ ] **Step 1: 验证租户隔离 — 商户 A 看不到商户 B 数据**

```php
it('merchant only sees own products', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = MerchantUser::factory()->for($tenantA)->create();
    Product::factory()->for($tenantA)->count(2)->create();
    Product::factory()->for($tenantB)->count(3)->create();

    $this->actingAs($userA, 'merchant');
    app()->instance(TenantContext::class, new TenantContext($tenantA->id, null, $tenantA->package->tier));

    livewire(ListProducts::class)->assertCountTableRecords(2);
});
```

- [ ] **Step 2-4: 实现 Product/Order Resource（参照截图列与 Action）**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: add merchant product and order resources with tenant isolation"
```

---

### Task 17: CouponResource + ApiKeyResource + TenantBillResource

**Files:**
- Create: `app/Filament/Merchant/Resources/CouponResource.php`
- Create: `app/Filament/Merchant/Resources/ApiKeyResource.php`
- Create: `app/Filament/Merchant/Resources/TenantBillResource.php`
- Create: `app/Filament/Merchant/Pages/MerchantDashboardPage.php`

- [ ] **Step 1-3: 实现三个 Resource + 商户概览页（Filament Stats + Table）**

- [ ] **Step 4: TenantBillResource 结算 Action（A 状态流转）**

```php
Action::make('settle')->action(function (TenantBill $record) {
    $record->update(['status' => BillStatus::Settled, 'paid_at' => now()]);
});
```

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: add merchant coupon, api key, billing resources"
```

---

## Phase 9: Vue3 嵌入面板 + 内部 API

### Task 18: 内部 API Controllers

**Files:**
- Create: `app/Http/Controllers/Internal/PlatformDashboardController.php`
- Create: `app/Http/Controllers/Internal/ApiMonitoringController.php`
- Create: `app/Http/Controllers/Internal/QueueOpsController.php`
- Create: `app/Http/Controllers/Internal/RiskReconciliationController.php`
- Create: `routes/internal.php`
- Test: `tests/Feature/Internal/PlatformDashboardApiTest.php`

**Interfaces:**
- Produces: `GET /api/internal/platform/dashboard` → JSON for Vue3 panels
- Consumes: platform guard auth

- [ ] **Step 1-4: 实现 4 个 Controller + 路由 + 测试**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: add internal API endpoints for Vue3 panels"
```

---

### Task 19: 4 个 Vue3 Echarts 面板

**Files:**
- Create: `resources/js/panels/PlatformDashboardPanel.vue`
- Create: `resources/js/panels/ApiMonitoringPanel.vue`
- Create: `resources/js/panels/QueueOpsPanel.vue`
- Create: `resources/js/panels/RiskReconciliationPanel.vue`
- Create: `resources/js/panels/platform-dashboard.js` (etc. mount entries)
- Create: `resources/views/filament/pages/vue-panel.blade.php`
- Create: `app/Filament/Platform/Pages/DashboardPage.php` (etc.)

- [ ] **Step 1: 创建 Filament Page 壳**

```php
// app/Filament/Platform/Pages/DashboardPage.php
class DashboardPage extends Page
{
    protected static string $view = 'filament.pages.vue-panel';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = '仪表盘';

    public function getVueEntry(): string {
        return 'resources/js/panels/platform-dashboard.js';
    }
}
```

- [ ] **Step 2: Vue 组件拉取内部 API 渲染 Echarts（5s 轮询 API 监控页）**

- [ ] **Step 3: 浏览器验证 4 面板数据渲染**

- [ ] **Step 4: Commit**

```bash
git commit -am "feat: add 4 Vue3 Echarts embedded panels"
```

---

## Phase 10: 开放 API v1

### Task 20: Auth Token 端点

**Files:**
- Create: `app/Domain/Api/AccessTokenService.php`
- Create: `app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/AuthTokenTest.php`

- [ ] **Step 1: 写失败测试**

```php
it('issues access token with valid credentials', function () {
    $key = ApiKey::factory()->create(['app_key' => 'AK_TEST', 'app_secret' => Hash::make('secret')]);
    $response = $this->postJson('/api/v1/auth/token', [
        'app_key' => 'AK_TEST', 'app_secret' => 'secret',
    ]);
    $response->assertOk()->assertJsonStructure(['data' => ['access_token','expires_in']]);
});
```

- [ ] **Step 2-4: 实现 + 测试通过**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: add API v1 auth token endpoints"
```

---

### Task 21: Products + Orders API

**Files:**
- Create: `app/Http/Controllers/Api/V1/ProductController.php`
- Create: `app/Http/Controllers/Api/V1/OrderController.php`
- Create: `app/Application/Api/ProductApiService.php`
- Create: `app/Application/Api/OrderApiService.php`
- Test: `tests/Feature/Api/V1/ProductApiTest.php`
- Test: `tests/Feature/Api/V1/OrderApiTest.php`

- [ ] **Step 1-5: 按 `docs/api/openapi.yaml` 实现全部端点 + 权限矩阵校验 + Commit**

```bash
git commit -am "feat: add API v1 products and orders endpoints"
```

---

### Task 22: Bills + Dashboard API

**Files:**
- Create: `app/Http/Controllers/Api/V1/BillController.php`
- Create: `app/Http/Controllers/Api/V1/DashboardController.php`
- Test: `tests/Feature/Api/V1/BillApiTest.php`

- [ ] **Step 1-4: 实现账单查询/导出 + 经营指标 API**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: add API v1 bills and dashboard endpoints"
```

---

## Phase 11: 队列任务 / 月结 / 风控

### Task 23: 核心 Queue Jobs

**Files:**
- Create: `app/Jobs/CloseExpiredOrderJob.php`
- Create: `app/Jobs/MonthlyBillingJob.php`
- Create: `app/Jobs/ApiUsageFlushJob.php`
- Create: `app/Jobs/RiskRuleScanJob.php`
- Create: `app/Domain/Billing/BillSettlementService.php`
- Create: `app/Domain/Risk/RuleEngine.php`
- Test: `tests/Unit/Domain/Billing/BillSettlementServiceTest.php`
- Test: `tests/Unit/Domain/Risk/RuleEngineTest.php`

- [ ] **Step 1: 月结半自动对账测试**

```php
it('generates discrepancy when merchant reported differs', function () {
    $bill = TenantBill::factory()->create([
        'total_receivable' => 8420.00,
        'merchant_reported_amount' => 8400.00,
    ]);
    app(BillSettlementService::class)->recordMerchantReport($bill, 8400.00);
    expect(ReconciliationDiscrepancy::count())->toBe(1);
    expect(ReconciliationDiscrepancy::first()->difference)->toBe(20.00);
});
```

- [ ] **Step 2-4: 实现 8 个 Job + RuleEngine 5 条规则 + 调度 `routes/console.php`**

```php
// routes/console.php
Schedule::job(new MonthlyBillingJob)->monthlyOn(1, '02:00');
Schedule::job(new RiskRuleScanJob)->hourly();
Schedule::job(new ApiUsageFlushJob)->dailyAt('00:05');
```

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: add billing, risk, and queue jobs with scheduler"
```

---

## Phase 12: 种子数据与集成验证

### Task 24: DatabaseSeeder 全套演示数据

**Files:**
- Create: all Seeders per `docs/database/schema-overview.md`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Seeder/DatabaseSeederTest.php`

- [ ] **Step 1: 实现 15 个 Seeder（套餐/平台管理员/12商户/商品/订单/账单/风控/API日志/队列日志）**

- [ ] **Step 2: 验证种子数据**

```bash
php artisan migrate:fresh --seed
# 验证：12+ 商户, 4285+ 订单, 平台管理员 admin@saas.com
```

- [ ] **Step 3: Commit**

```bash
git commit -am "feat: add comprehensive demo seeders matching UI screenshots"
```

---

### Task 25: 端到端冒烟测试

**Files:**
- Create: `tests/Feature/Smoke/PlatformSmokeTest.php`
- Create: `tests/Feature/Smoke/ApiSmokeTest.php`

- [ ] **Step 1: 平台后台冒烟**

```php
it('platform admin can access all panel pages', function () {
    $admin = PlatformUser::factory()->create();
    $this->actingAs($admin, 'platform')
        ->get('/platform/tenants')->assertOk();
    $this->get('/platform/packages')->assertOk();
    $this->get('/platform/dashboard')->assertOk();
});
```

- [ ] **Step 2: API 全链路冒烟（token → products → orders → bills）**

- [ ] **Step 3: 全量测试**

```bash
php artisan test
# Expected: ALL PASS
```

- [ ] **Step 4: Commit + 更新 README dev 命令**

```bash
git commit -am "test: add smoke tests for platform and API"
```

---

## Spec Coverage Self-Review

| Spec 要求 | 对应 Task |
|-----------|-----------|
| PHP 8.3 Enum + readonly TenantContext | Task 3, 4 |
| Filament v3 14 纯页面 | Task 14-17 |
| 4 个 Vue3 面板 | Task 19 |
| Octane + 内存清理 | Task 10 |
| Redis Lua 锁/限流/延迟/死信 | Task 8, 9 |
| 共享库 tenant_id Scope + SqlGuard | Task 4, 10 |
| 商户独立收款 + 账单状态流转 + 支付预留 | Task 6, 17, 23 |
| /api/v1 双 Token | Task 11, 20-22 |
| 双表 + Impersonation | Task 12, 13 |
| API 分级配额 | Task 8, 11 |
| SPU+SKU 预留 | Task 6 |
| 轻量规则引擎 5 条 | Task 23 |
| 月结半自动差异单 | Task 23 |
| 8 个 Queue Job | Task 23 |
| 种子数据对齐截图 | Task 24 |
| OpenAPI 22 端点 | Task 20-22 |

**Placeholder scan:** 无 TBD/TODO。Task 14-17 Filament 列配置参照截图，实施时按 Resource 逐步填充。

---

## 执行顺序与预估

| Phase | Tasks | 预估工时 |
|-------|-------|----------|
| 1 脚手架 | 1-2 | 2h |
| 2 领域核心 | 3-4 | 3h |
| 3 数据库 | 5-7 | 6h |
| 4 Redis | 8-9 | 4h |
| 5 中间件 | 10-11 | 3h |
| 6 认证 | 12-13 | 3h |
| 7 平台 Filament | 14-15 | 5h |
| 8 商户 Filament | 16-17 | 5h |
| 9 Vue3 面板 | 18-19 | 6h |
| 10 开放 API | 20-22 | 6h |
| 11 Jobs/风控/月结 | 23 | 4h |
| 12 种子+冒烟 | 24-25 | 3h |
| **合计** | **25 Tasks** | **~50h** |

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-02-saas-ecommerce-platform.md`.

**两种执行方式：**

1. **Subagent-Driven（推荐）** — 每个 Task 派发独立 subagent，任务间两阶段审查，迭代快
2. **Inline Execution** — 当前会话按 Task 顺序执行，每 Phase 结束后设检查点

**请选择执行方式？**
