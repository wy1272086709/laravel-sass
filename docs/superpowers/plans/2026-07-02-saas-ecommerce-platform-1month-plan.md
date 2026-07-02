# 多商户 SaaS 电商开放中台 1 个月分层开发计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 基于已批准的 SDD 与 OpenAPI 规范，在 1 个月内交付可演示、可联调、可压测的多商户 SaaS 电商开放中台 MVP。

**Architecture:** 采用模块化单体 Laravel 11 架构，以 `readonly TenantContext`、`TenantScope`、双 Guard、Octane 中间件链为基础。开发顺序遵循“底层基座 -> 数据持久 -> 管理后台 -> API 网关 -> 调度队列 -> 可视化 -> 测试与文档”，确保每一阶段结束时都可独立验收。

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
**OpenAPI:** `docs/api/openapi.yaml`
**Schema:** `docs/database/schema-overview.md`

---

## 月度排期总览

| 周次 | 阶段 | 里程碑 |
|------|------|--------|
| 第 1 周 | 阶段 1-2 | 底层基座、迁移、模型、Factory、最小种子数据可运行 |
| 第 2 周 | 阶段 3-4 | Filament 双后台 CRUD 全部打通，API 网关基础完成 |
| 第 3 周 | 阶段 5-6 | 队列调度、月结对账、任务中心、4 个 Vue3 面板可演示 |
| 第 4 周 | 阶段 7 | 压测、测试补齐、文档封板、交付验收 |

## 阶段与文件边界

| 层 | 主要路径 |
|----|----------|
| 底层基座 | `app/Domain/*`, `app/Infrastructure/*`, `app/Http/Middleware/*`, `bootstrap/app.php` |
| 数据持久 | `database/migrations/*`, `app/Models/*`, `database/factories/*`, `database/seeders/*` |
| 管理后台 | `app/Filament/Platform/*`, `app/Filament/Merchant/*`, `resources/views/filament/*` |
| API 网关 | `app/Http/Controllers/Api/V1/*`, `routes/api.php`, `config/auth.php` |
| 调度任务 | `app/Jobs/*`, `app/Domain/Billing/*`, `app/Domain/Risk/*`, `routes/console.php` |
| 可视化 | `resources/js/panels/*`, `app/Http/Controllers/Internal/*`, `vite.config.js` |
| 质量与交付 | `tests/**/*`, `docs/**/*`, `README.md` |

---

## 阶段 1：底层架构基座

**时间盒：** 第 1 周，2-3 天  
**目标：** 先把多租户上下文、枚举契约、Octane 清理、Redis 基础工具打牢，后续所有功能都建立在这套基础之上。

**Files:**
- Create: `app/Domain/Enums/*.php`
- Create: `app/Domain/Tenant/TenantContext.php`
- Create: `app/Domain/Tenant/TenantScope.php`
- Create: `app/Http/Middleware/ResolveTenantContext.php`
- Create: `app/Http/Middleware/ApiRateLimitMiddleware.php`
- Create: `app/Infrastructure/Octane/OctaneTenantCleanupMiddleware.php`
- Create: `app/Infrastructure/Octane/SqlTenantGuard.php`
- Create: `app/Infrastructure/Redis/LuaDistributedLock.php`
- Create: `app/Infrastructure/Redis/SlidingWindowRateLimiter.php`
- Create: `app/Infrastructure/Redis/DelayQueue.php`
- Create: `app/Infrastructure/Redis/DeadLetterQueue.php`
- Create: `app/Infrastructure/Redis/ApiDailyCounter.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Unit/Domain/Enums/*`
- Test: `tests/Unit/Domain/Tenant/*`
- Test: `tests/Unit/Infrastructure/Redis/*`
- Test: `tests/Feature/Middleware/TenantCleanupTest.php`

**Interfaces:**
- Consumes: SDD 中 `TenantContext`、API 配额分级策略、Redis 组件约束
- Produces: `TenantContext::__construct(?int $tenantId, ?int $impersonatorId, PackageTier $tier)`
- Produces: `SlidingWindowRateLimiter::tooManyAttempts(string $key, int $limit, int $windowSeconds): bool`
- Produces: `LuaDistributedLock::acquire(string $key, int $ttlMs): ?string`

- [ ] **Step 1: 先写失败测试，锁定上下文与限流契约**

```php
it('creates readonly tenant context', function () {
    $context = new TenantContext(1, 9, PackageTier::Professional);

    expect($context->tenantId)->toBe(1);
    expect($context->impersonatorId)->toBe(9);
    expect($context->tier)->toBe(PackageTier::Professional);
});
```

- [ ] **Step 2: 实现 Enum、TenantContext、TenantScope**

Run:

```bash
php artisan test tests/Unit/Domain
```

- [ ] **Step 3: 实现 Octane 中间件与 Redis 基础工具**

Run:

```bash
php artisan test tests/Unit/Infrastructure tests/Feature/Middleware
```

- [ ] **Step 4: 验收标准**

**Acceptance Criteria:**
- 16 个 Enum 已落地，核心值与 SDD 一致
- `TenantContext` 为 `readonly`，能区分平台、商户、Impersonation 三种上下文
- `OctaneTenantCleanupMiddleware` 可在请求结束后重置租户上下文
- `SlidingWindowRateLimiter`、`LuaDistributedLock`、`DelayQueue`、`DeadLetterQueue`、`ApiDailyCounter` 都有最小可用实现和测试

---

## 阶段 2：数据库迁移、多租户 Trait、Factory 测试数据

**时间盒：** 第 1 周，2-3 天  
**目标：** 一次性落地数据库骨架与模型层，保证第 2 周开始可以直接搭页面和 API，不再返工数据结构。

**Files:**
- Create: `database/migrations/*.php`
- Create: `app/Models/Concerns/BelongsToTenant.php`
- Create: `app/Models/Platform/*.php`
- Create: `app/Models/Tenant/*.php`
- Create: `app/Models/Product/*.php`
- Create: `app/Models/Order/*.php`
- Create: `app/Models/Marketing/*.php`
- Create: `app/Models/Api/*.php`
- Create: `app/Models/Billing/*.php`
- Create: `app/Models/Risk/*.php`
- Create: `database/factories/**/*.php`
- Create: `database/seeders/PackageSeeder.php`
- Create: `database/seeders/PlatformAdminSeeder.php`
- Create: `database/seeders/TenantSeeder.php`
- Create: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Database/MigrationSmokeTest.php`
- Test: `tests/Feature/Database/TenantScopeModelTest.php`

**Interfaces:**
- Consumes: `TenantContext`, `TenantScope`, all backed enums
- Produces: `BelongsToTenant` trait
- Produces: all Eloquent models with enum casts and relations

- [ ] **Step 1: 写迁移和多租户 Trait 的失败测试**

```php
it('migrates critical tables', function () {
    $this->artisan('migrate');

    expect(Schema::hasTable('tenants'))->toBeTrue();
    expect(Schema::hasTable('products'))->toBeTrue();
    expect(Schema::hasTable('tenant_bills'))->toBeTrue();
    expect(Schema::hasTable('api_keys'))->toBeTrue();
});
```

- [ ] **Step 2: 完成迁移与模型**

Run:

```bash
php artisan migrate:fresh
```

- [ ] **Step 3: 完成 Factory 与最小 Seeder**

Run:

```bash
php artisan migrate:fresh --seed
```

- [ ] **Step 4: 验收标准**

**Acceptance Criteria:**
- 28 张核心表可成功迁移
- `tenant_bills` 已包含支付预留字段：`payment_channel`、`external_transaction_no`、`paid_at`、`payment_meta`
- `BelongsToTenant` 能自动写入 `tenant_id`
- 平台用户、商户、商品、订单、账单、API 密钥的 Factory 可直接用于测试
- 最小 Seeder 可提供：1 个平台管理员、3 个商户、基础商品与订单样例

---

## 阶段 3：Filament 双端后台全部 CRUD Resource 页面

**时间盒：** 第 2 周前半周  
**目标：** 先交付平台端与商户端所有纯 Filament 页面，让后台管理能力完整可用。

**Files:**
- Create: `app/Providers/Filament/PlatformPanelProvider.php`
- Create: `app/Providers/Filament/MerchantPanelProvider.php`
- Create: `app/Filament/Pages/Auth/UnifiedLogin.php`
- Create: `app/Filament/Platform/Resources/TenantResource.php`
- Create: `app/Filament/Platform/Resources/PackageResource.php`
- Create: `app/Filament/Platform/Resources/PlatformRoleResource.php`
- Create: `app/Filament/Platform/Pages/PlatformProfilePage.php`
- Create: `app/Filament/Merchant/Resources/ProductResource.php`
- Create: `app/Filament/Merchant/Resources/OrderResource.php`
- Create: `app/Filament/Merchant/Resources/CouponResource.php`
- Create: `app/Filament/Merchant/Resources/ApiKeyResource.php`
- Create: `app/Filament/Merchant/Resources/TenantBillResource.php`
- Create: `app/Filament/Merchant/Pages/MerchantDashboardPage.php`
- Create: `app/Http/Controllers/ImpersonationController.php`
- Test: `tests/Feature/Auth/DualGuardLoginTest.php`
- Test: `tests/Feature/Auth/ImpersonationTest.php`
- Test: `tests/Feature/Filament/Platform/*.php`
- Test: `tests/Feature/Filament/Merchant/*.php`

**Interfaces:**
- Consumes: 双 Guard、`TenantContext`、全部模型与关系
- Produces: `platform` / `merchant` 双 Panel
- Produces: “进入商户后台 / 返回平台总后台”切换能力

- [ ] **Step 1: 配置双 Guard 与统一登录页**

Run:

```bash
php artisan test tests/Feature/Auth/DualGuardLoginTest.php
```

- [ ] **Step 2: 生成并实现所有 Resource 与 Page**

Run:

```bash
php artisan make:filament-resource Tenant --panel=platform
php artisan make:filament-resource Product --panel=merchant
```

- [ ] **Step 3: 接入 Impersonation**

Run:

```bash
php artisan test tests/Feature/Auth/ImpersonationTest.php
```

- [ ] **Step 4: 验收标准**

**Acceptance Criteria:**
- 平台端可访问：商户管理、套餐配置、角色权限、个人中心
- 商户端可访问：店铺概览、商品管理、订单管理、营销优惠、API 密钥、月度账单
- 登录页支持平台管理员 / 商户 Tab 切换
- 平台管理员可成功进入任一商户后台并返回平台
- 所有 CRUD 页面满足租户隔离，不会跨商户看到数据

---

## 阶段 4：Sanctum API 网关、限流、全局异常中间件

**时间盒：** 第 2 周后半周  
**目标：** 按 OpenAPI 规范落地 `/api/v1` 网关，确保第三方系统能进行认证、查询、写入与限流。

**Files:**
- Create: `app/Domain/Api/AccessTokenService.php`
- Create: `app/Application/Api/QuotaPolicyService.php`
- Create: `app/Http/Middleware/ApiAuthMiddleware.php`
- Create: `app/Http/Middleware/ApiExceptionResponseMiddleware.php`
- Create: `app/Http/Controllers/Api/V1/AuthController.php`
- Create: `app/Http/Controllers/Api/V1/ProductController.php`
- Create: `app/Http/Controllers/Api/V1/OrderController.php`
- Create: `app/Http/Controllers/Api/V1/BillController.php`
- Create: `app/Http/Controllers/Api/V1/DashboardController.php`
- Modify: `routes/api.php`
- Modify: `config/auth.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/Api/V1/AuthTokenTest.php`
- Test: `tests/Feature/Api/V1/ProductApiTest.php`
- Test: `tests/Feature/Api/V1/OrderApiTest.php`
- Test: `tests/Feature/Api/V1/BillApiTest.php`
- Test: `tests/Feature/Api/V1/RateLimitTest.php`

**Interfaces:**
- Consumes: `docs/api/openapi.yaml`
- Produces: `/api/v1/auth/*`, `/api/v1/products*`, `/api/v1/orders*`, `/api/v1/bills*`, `/api/v1/dashboard/*`
- Produces: 统一错误格式 `{ code, message, data }`

- [ ] **Step 1: 先写 Auth 与 RateLimit 失败测试**

```php
it('returns 429 when basic tier exceeds quota', function () {
    $response = $this->withToken('fake-token')->getJson('/api/v1/products');

    $response->assertStatus(429);
});
```

- [ ] **Step 2: 优先实现 Auth、Products、Orders**

Run:

```bash
php artisan test tests/Feature/Api/V1/AuthTokenTest.php tests/Feature/Api/V1/ProductApiTest.php tests/Feature/Api/V1/OrderApiTest.php
```

- [ ] **Step 3: 实现 Bills、Dashboard 与全局异常中间件**

Run:

```bash
php artisan test tests/Feature/Api/V1
```

- [ ] **Step 4: 验收标准**

**Acceptance Criteria:**
- OpenAPI 中 22 个对外端点至少覆盖核心 19 个 MVP 端点（Auth / Products / Orders / Bills / Dashboard）
- `app_key + app_secret` 可换取 `AccessToken`
- API 权限矩阵生效：`product_query` / `order_manage` / `dashboard_read` / `bill_query`
- 基础版超额返回 429；专业/企业版允许软超额，150% 全局阻断
- 所有异常统一返回规范 JSON，包含 `40101`、`40301`、`40401`、`42901`、`42201`

---

## 阶段 5：延迟队列、定时对账、任务调度中心

**时间盒：** 第 3 周前半周  
**目标：** 补上异步任务与月结闭环，确保业务不是“静态 CRUD”，而是真正可运行的中台。

**Files:**
- Create: `app/Jobs/CloseExpiredOrderJob.php`
- Create: `app/Jobs/MonthlyBillingJob.php`
- Create: `app/Jobs/GenerateApiBillJob.php`
- Create: `app/Jobs/RiskRuleScanJob.php`
- Create: `app/Jobs/MerchantWelcomeEmailJob.php`
- Create: `app/Jobs/InventoryAlertJob.php`
- Create: `app/Jobs/SyncLogisticsJob.php`
- Create: `app/Jobs/ApiUsageFlushJob.php`
- Create: `app/Domain/Billing/BillSettlementService.php`
- Create: `app/Domain/Risk/RuleEngine.php`
- Create: `app/Http/Controllers/Internal/QueueOpsController.php`
- Modify: `routes/console.php`
- Test: `tests/Unit/Domain/Billing/BillSettlementServiceTest.php`
- Test: `tests/Unit/Domain/Risk/RuleEngineTest.php`
- Test: `tests/Feature/Jobs/MonthlyBillingJobTest.php`
- Test: `tests/Feature/Internal/QueueOpsApiTest.php`

**Interfaces:**
- Consumes: Redis 工具层、订单状态机、账单模型、风险规则模型
- Produces: 月结任务、差异单生成、任务中心数据接口

- [ ] **Step 1: 先写账单与风控失败测试**

```php
it('creates discrepancy when merchant reported amount differs', function () {
    $bill = TenantBill::factory()->create([
        'total_receivable' => 8420,
        'merchant_reported_amount' => 8400,
    ]);

    app(BillSettlementService::class)->recordMerchantReport($bill, 8400);

    expect(ReconciliationDiscrepancy::count())->toBe(1);
});
```

- [ ] **Step 2: 实现 8 个 Job 与调度注册**

Run:

```bash
php artisan schedule:list
```

- [ ] **Step 3: 实现任务中心内部接口**

Run:

```bash
php artisan test tests/Feature/Jobs tests/Feature/Internal/QueueOpsApiTest.php
```

- [ ] **Step 4: 验收标准**

**Acceptance Criteria:**
- `CloseExpiredOrderJob` 可关闭超时未支付订单
- `MonthlyBillingJob` 可生成月账单与应收统计
- 手工填写 `merchant_reported_amount` 后可自动生成差异单
- 风控规则引擎至少实现 5 条内置规则
- `schedule:list` 中可看到月结、风险扫描、API 落库等定时任务
- 队列中心有真实的 `queue_job_logs` 数据源

---

## 阶段 6：Vue3 嵌入式可视化组件开发

**时间盒：** 第 3 周后半周  
**目标：** 完成 4 个核心 Vue3 嵌入面板，让平台后台从“能用”提升到“能展示技术亮点”。

**Files:**
- Create: `resources/js/panels/PlatformDashboardPanel.vue`
- Create: `resources/js/panels/ApiMonitoringPanel.vue`
- Create: `resources/js/panels/QueueOpsPanel.vue`
- Create: `resources/js/panels/RiskReconciliationPanel.vue`
- Create: `resources/js/panels/platform-dashboard.js`
- Create: `resources/js/panels/api-monitoring.js`
- Create: `resources/js/panels/queue-ops.js`
- Create: `resources/js/panels/risk-reconciliation.js`
- Create: `resources/views/filament/pages/vue-panel.blade.php`
- Create: `app/Http/Controllers/Internal/PlatformDashboardController.php`
- Create: `app/Http/Controllers/Internal/ApiMonitoringController.php`
- Create: `app/Http/Controllers/Internal/RiskReconciliationController.php`
- Modify: `vite.config.js`
- Test: `tests/Feature/Internal/PlatformDashboardApiTest.php`
- Test: `tests/Feature/Internal/ApiMonitoringApiTest.php`
- Test: `tests/Feature/Internal/RiskReconciliationApiTest.php`

**Interfaces:**
- Consumes: `/api/internal/platform/dashboard`
- Consumes: `/api/internal/platform/api-monitor`
- Consumes: `/api/internal/platform/queue-ops`
- Consumes: `/api/internal/platform/risk-recon`
- Produces: 4 个 Filament 页面内可挂载的 Vue 图表组件

- [ ] **Step 1: 先实现内部 API，再挂 Vue 壳**

Run:

```bash
php artisan test tests/Feature/Internal
```

- [ ] **Step 2: 按面板逐个挂载 Echarts**

Run:

```bash
npm run build
```

- [ ] **Step 3: 验收标准**

**Acceptance Criteria:**
- 平台仪表盘可展示 GMV 趋势、套餐占比、队列健康
- API 监控页可展示 24h 调用趋势、Top10、实时日志
- 队列中心可展示任务状态环图与任务列表
- 风控对账页可展示 7 天风控命中趋势和差异单表格
- 4 个页面都能在 Filament 中正常挂载，前后端数据字段一致

---

## 阶段 7：本地压测、单元测试、文档完善

**时间盒：** 第 4 周  
**目标：** 封板前集中做性能、测试与交付材料，确保项目不仅能跑，还能稳定演示、便于答辩和面试说明。

**Files:**
- Create: `tests/Feature/Smoke/PlatformSmokeTest.php`
- Create: `tests/Feature/Smoke/ApiSmokeTest.php`
- Create: `tests/Performance/ApiRateLimitBenchTest.php`
- Modify: `README.md`
- Modify: `docs/superpowers/specs/2026-07-02-saas-ecommerce-platform-design.md`
- Modify: `docs/architecture/layered-architecture.md`
- Modify: `docs/database/schema-overview.md`
- Modify: `docs/api/openapi.yaml`
- Create: `docs/api/internal.yaml`
- Create: `docs/testing/local-benchmark.md`

**Interfaces:**
- Consumes: 所有阶段交付物
- Produces: 可复现测试说明、压测步骤、最终文档集

- [ ] **Step 1: 补齐 Smoke / Integration / Performance 测试**

Run:

```bash
php artisan test
```

- [ ] **Step 2: 本地压测核心链路**

Run:

```bash
php artisan octane:start --server=swoole --host=127.0.0.1 --port=8000
```

压测目标：

```text
1. /api/v1/auth/token
2. /api/v1/products
3. /api/v1/orders
4. /api/internal/platform/api-monitor
```

- [ ] **Step 3: 更新交付文档**

Run:

```bash
rg "待建|TODO|TBD" /Users/mac/laravelProj/docs
```

- [ ] **Step 4: 验收标准**

**Acceptance Criteria:**
- `php artisan test` 全量通过
- 本地 Octane + Redis + MySQL 组合下，关键 API 可完成压测并输出记录
- `README.md` 含启动、迁移、种子、测试、压测命令
- `docs/api/internal.yaml` 补齐内部接口
- 所有对外交付文档无明显占位词

---

## 每周验收门槛

| 周次 | 必须达成 |
|------|----------|
| 第 1 周 | 项目可 `migrate:fresh --seed`，底层能力和模型层通过测试 |
| 第 2 周 | 双后台 CRUD 与 API 网关可联调 |
| 第 3 周 | 队列、月结、风控、4 个 Vue3 面板可演示 |
| 第 4 周 | 测试、压测、文档全部封板 |

## 风险与切分建议

- 如果第 2 周末 API 网关未稳定，优先保证 `Auth + Products GET + Orders GET + Bills GET`，其余写接口延后
- 如果第 3 周 Vue3 面板时间不足，优先级依次为：`PlatformDashboardPanel` > `ApiMonitoringPanel` > `QueueOpsPanel` > `RiskReconciliationPanel`
- 如果第 4 周压测时间不足，至少保留 `auth/token`、`products`、`orders` 三条链路的 baseline 结果

## Spec Coverage Self-Review

| 用户要求 | 覆盖阶段 |
|----------|----------|
| 1. 底层架构基座 | 阶段 1 |
| 2. 数据库迁移、多租户 Trait、Factory | 阶段 2 |
| 3. Filament 双端后台全部 CRUD | 阶段 3 |
| 4. Sanctum API 网关、限流、异常中间件 | 阶段 4 |
| 5. 延迟队列、定时对账、任务调度中心 | 阶段 5 |
| 6. Vue3 嵌入式可视化组件 | 阶段 6 |
| 7. 本地压测、单元测试、文档完善 | 阶段 7 |

无缺口；每一阶段均明确了文件路径与验收标准。

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-02-saas-ecommerce-platform-1month-plan.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
