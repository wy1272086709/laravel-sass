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

## 阶段 3：Filament 双端后台 CRUD 与系统切换

**时间盒：** 第 2 周前半周  
**目标：** 分批交付平台端与商户端 Filament 页面，先把核心经营链路跑通，再补齐营销/API/账单和 Impersonation。

### 阶段 3-1：前端依赖与核心 CRUD 骨架

**目标：** 统一使用 `pnpm` 安装前端依赖，并交付第一批可访问的 Filament Resource：平台端商户/套餐，商户端商品/订单。

**Files:**
- Modify: `package.json`
- Create: `pnpm-lock.yaml`
- Create/Modify: `vite.config.js`
- Create: `app/Filament/Platform/Resources/TenantResource.php`
- Create: `app/Filament/Platform/Resources/PackageResource.php`
- Create: `app/Filament/Merchant/Resources/ProductResource.php`
- Create: `app/Filament/Merchant/Resources/OrderResource.php`
- Test: `tests/Feature/Filament/Platform/TenantResourceTest.php`
- Test: `tests/Feature/Filament/Platform/PackageResourceTest.php`
- Test: `tests/Feature/Filament/Merchant/ProductResourceTest.php`
- Test: `tests/Feature/Filament/Merchant/OrderResourceTest.php`

**Commands:**

```bash
pnpm add vue@3 echarts vue-echarts
pnpm add -D @vitejs/plugin-vue
pnpm build
php artisan test tests/Feature/Filament
```

**Acceptance Criteria:**
- 仅使用 `pnpm` 管理前端依赖，生成 `pnpm-lock.yaml`，不引入 `npm` / `yarn` 锁文件。
- `pnpm build` 可通过。
- 平台端可访问商户管理、套餐配置 Resource。
- 商户端可访问商品管理、订单管理 Resource。
- 商户端 Resource 查询受 `TenantContext` / `TenantScope` 约束，不跨商户展示数据。

### 阶段 3-2：商户经营页面补齐

**目标：** 补齐商户端经营后台的剩余纯 Filament 页面，让商户日常操作闭环。

**Files:**
- Create: `app/Filament/Merchant/Resources/CouponResource.php`
- Create: `app/Filament/Merchant/Resources/ApiKeyResource.php`
- Create: `app/Filament/Merchant/Resources/TenantBillResource.php`
- Create: `app/Filament/Merchant/Pages/MerchantDashboardPage.php`
- Test: `tests/Feature/Filament/Merchant/CouponResourceTest.php`
- Test: `tests/Feature/Filament/Merchant/ApiKeyResourceTest.php`
- Test: `tests/Feature/Filament/Merchant/TenantBillResourceTest.php`

**Acceptance Criteria:**
- 商户端可访问店铺概览、营销优惠、API 密钥、月度账单。
- API 密钥列表隐藏 `app_secret`，权限字段按 `ApiPermission` 枚举展示/编辑。
- 月度账单展示支付预留字段与账单状态，不对接真实支付。

### 阶段 3-3：平台权限、统一登录与 Impersonation

**目标：** 补齐平台侧权限管理、个人中心、统一登录页和平台进入商户后台链路。

**Files:**
- Create: `app/Filament/Pages/Auth/UnifiedLogin.php`
- Create: `app/Filament/Platform/Resources/PlatformRoleResource.php`
- Create: `app/Filament/Platform/Pages/PlatformProfilePage.php`
- Create: `app/Http/Controllers/ImpersonationController.php`
- Test: `tests/Feature/Auth/DualGuardLoginTest.php`
- Test: `tests/Feature/Auth/ImpersonationTest.php`
- Test: `tests/Feature/Filament/Platform/PlatformRoleResourceTest.php`

**Acceptance Criteria:**
- 登录页支持平台管理员 / 商户 Tab 切换。
- 平台端可访问角色权限、个人中心。
- 平台管理员可成功进入任一商户后台并返回平台。
- Impersonation 全程写入审计日志。

### 阶段 3 总文件边界

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

- [ ] **Step 1: 完成阶段 3-1：前端依赖与核心 CRUD 骨架**

Run:

```bash
pnpm build
php artisan test tests/Feature/Filament
```

- [ ] **Step 2: 完成阶段 3-2：商户经营页面补齐**

Run:

```bash
php artisan test tests/Feature/Filament/Merchant
```

- [ ] **Step 3: 完成阶段 3-3：平台权限、统一登录与 Impersonation**

Run:

```bash
php artisan test tests/Feature/Auth/DualGuardLoginTest.php
php artisan test tests/Feature/Auth/ImpersonationTest.php
```

- [ ] **Step 4: 全阶段验收**

Run:

```bash
pnpm build
php artisan test tests/Feature/Auth tests/Feature/Filament
```

- [ ] **Step 5: 验收标准**

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

### 阶段 4-1：API Auth、Token 签发与错误信封

**目标：** 先打通开放 API 的认证入口、Bearer Token 校验、租户上下文注入和统一错误响应，让后续业务接口可以安全接入。

**Files:**
- Create: `app/Domain/Api/AccessTokenService.php`
- Create: `app/Http/Middleware/ApiAuthMiddleware.php`
- Create: `app/Http/Middleware/ApiExceptionResponseMiddleware.php`
- Create: `app/Http/Controllers/Api/V1/AuthController.php`
- Create: `routes/api.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Models/Api/ApiKey.php`
- Test: `tests/Feature/Api/V1/AuthTokenTest.php`

**Acceptance Criteria:**
- `POST /api/v1/auth/token` 可用 `app_key + app_secret` 换取 `access_token` 与 `refresh_token`。
- `POST /api/v1/auth/token/refresh` 可用 refresh token 换取新 token。
- `DELETE /api/v1/auth/token` 可吊销当前 access token。
- 无效/过期 Bearer token 返回 `{ code:40101, message:"Invalid or expired AccessToken", data:null }`。
- 通过认证后请求内绑定 `TenantContext`，后续租户域查询自动隔离。
- 验证失败返回 `{ code:42201, message:"Validation failed", data:{...} }`。

### 阶段 4-2：Products / Orders MVP 接口

**目标：** 优先交付开放 API 核心读写链路，覆盖商品与订单的 MVP 端点和权限校验。

**Files:**
- Create: `app/Http/Controllers/Api/V1/ProductController.php`
- Create: `app/Http/Controllers/Api/V1/OrderController.php`
- Create/Modify: API permission middleware / helpers
- Test: `tests/Feature/Api/V1/ProductApiTest.php`
- Test: `tests/Feature/Api/V1/OrderApiTest.php`

**Acceptance Criteria:**
- `product_query` 可访问商品列表/详情。
- `order_manage` 可创建/查询/更新订单。
- 缺权限返回 `40301`。
- 所有资源查询受 `TenantScope` 约束。

### 阶段 4-3：Bills / Dashboard / RateLimit 完整验收

**目标：** 补齐账单、经营看板、真实套餐配额读取和全局异常映射。

**Files:**
- Create: `app/Http/Controllers/Api/V1/BillController.php`
- Create: `app/Http/Controllers/Api/V1/DashboardController.php`
- Modify: `app/Http/Middleware/ApiRateLimitMiddleware.php`
- Test: `tests/Feature/Api/V1/BillApiTest.php`
- Test: `tests/Feature/Api/V1/RateLimitTest.php`

**Acceptance Criteria:**
- 账单与 Dashboard 接口返回统一成功信封。
- 配额从 `packages.api_quota_daily` 读取。
- 基础版超额 429；专业/企业版 150% 全局阻断。
- 常见异常统一映射为 `40101`、`40301`、`40401`、`42201`、`42901`。

### 阶段 4 问题记录

| 日期 | 阶段 | 状态 | 问题 | 处理记录 |
|------|------|------|------|----------|
| 2026-07-05 | 4-1 | resolved | OpenAPI 中 `/auth/token` 重复定义，YAML 解析可能覆盖 `post` 或 `delete`。 | 已合并为同一个 path 下的 `post` 与 `delete`，与 Laravel 路由保持一致。 |
| 2026-07-05 | 4-1 | resolved | AccessToken/RefreshToken 是否都使用 Sanctum `personal_access_tokens` 存储，还是独立 refresh token 表。 | MVP 阶段采用 Sanctum token name/ability 区分 access 与 refresh；refresh token 单次使用后删除，后续若要加强轮换审计再拆独立表。 |
| 2026-07-05 | 4-2 | open | OpenAPI 发货/退款请求包含 `tracking_no`、`carrier`、`amount`，但当前 `orders` 表没有物流与退款审计字段。 | 4-2 先按状态流转 MVP 实现 ship/cancel/refund，不持久化额外字段；后续如需物流轨迹和退款明细再补迁移与审计表。 |
| 2026-07-05 | 4-2 | resolved | Products API 使用隐式路由模型绑定时，模型可能在 `api.auth` 绑定租户上下文前解析，导致跨租户资源绕过 `TenantScope`。 | 已改为控制器内显式 `Product::query()->findOrFail()`，确保查询发生在 `api.auth` 注入 `TenantContext` 之后；新增跨租户 404 测试覆盖。 |

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

- [x] **Step 1: 完成阶段 4-1：API Auth、Token 签发与错误信封**

Run:

```bash
php artisan test tests/Feature/Api/V1/AuthTokenTest.php
```

Result: 2026-07-05 已通过 `php artisan test tests/Feature/Api/V1/AuthTokenTest.php`、`php artisan test`、`php artisan migrate:fresh --seed`、`pnpm build`。

- [x] **Step 2: 完成阶段 4-2：Products / Orders MVP 接口**

Run:

```bash
php artisan test tests/Feature/Api/V1/ProductApiTest.php tests/Feature/Api/V1/OrderApiTest.php
```

Result: 2026-07-05 已通过 `php artisan test tests/Feature/Api/V1/ProductApiTest.php tests/Feature/Api/V1/OrderApiTest.php`、`php artisan test`、`php artisan migrate:fresh --seed`、`pnpm build`。

- [x] **Step 3: 完成阶段 4-3：Bills / Dashboard / RateLimit 完整验收**

Run:

```bash
php artisan test tests/Feature/Api/V1
```

Result: 2026-07-05 已通过 `php artisan test tests/Feature/Api/V1/BillApiTest.php tests/Feature/Api/V1/DashboardApiTest.php tests/Feature/Api/V1/RateLimitApiTest.php`、`php artisan test`、`php artisan migrate:fresh --seed`、`pnpm build`。

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

### 阶段 5-1：账单闭环与核心异步 Job

**目标：** 先完成月结账单、API 用量落库、超时订单关闭和基础运营 Job，让财务闭环能跑起来。

**Files:**
- Create: `docs/architecture/billing-design.md`
- Create: `docs/architecture/queue-design.md`
- Create: `app/Domain/Billing/BillSettlementService.php`
- Create: `app/Jobs/CloseExpiredOrderJob.php`
- Create: `app/Jobs/MonthlyBillingJob.php`
- Create: `app/Jobs/GenerateApiBillJob.php`
- Create: `app/Jobs/ApiUsageFlushJob.php`
- Create: `app/Jobs/MerchantWelcomeEmailJob.php`
- Create: `app/Jobs/InventoryAlertJob.php`
- Create: `app/Jobs/SyncLogisticsJob.php`
- Test: `tests/Unit/Domain/Billing/BillSettlementServiceTest.php`
- Test: `tests/Feature/Jobs/MonthlyBillingJobTest.php`
- Test: `tests/Feature/Jobs/CoreJobsTest.php`

**Acceptance Criteria:**
- 账单与队列设计文档先行完成，明确公式、幂等、日志、调度与测试边界。
- `BillSettlementService` 可记录商户回填金额，并在存在差异时生成 `ReconciliationDiscrepancy`。
- `MonthlyBillingJob` 可按租户/月生成账单，应收统计可复算。
- `ApiUsageFlushJob` 可将 Redis 当日 API 计数落库到 `api_usage_daily`。
- `CloseExpiredOrderJob` 可关闭超时未支付订单。
- 轻量运营 Job 可写入 `queue_job_logs`，便于 5-3 任务中心读取真实数据。

### 阶段 5-2：风控规则引擎与扫描 Job

**目标：** 实现至少 5 条内置风控规则，并通过扫描 Job 生成可追踪的风险告警。

**Files:**
- Create: `app/Domain/Risk/RuleEngine.php`
- Create: `app/Jobs/RiskRuleScanJob.php`
- Test: `tests/Unit/Domain/Risk/RuleEngineTest.php`
- Test: `tests/Feature/Jobs/RiskRuleScanJobTest.php`

**Acceptance Criteria:**
- 规则引擎至少覆盖大额订单、退款率异常、API 调用突增、低库存高销量、账单差异。
- `RiskRuleScanJob` 可生成 `risk_alerts`，且不会重复生成同一批次告警。

### 阶段 5-3：调度注册与任务中心内部接口

**目标：** 注册定时任务，并提供内部任务中心接口读取 `queue_job_logs`、延迟队列和死信队列状态。

**Files:**
- Create: `app/Http/Controllers/Internal/QueueOpsController.php`
- Modify: `routes/console.php`
- Modify/Create: internal routes
- Test: `tests/Feature/Internal/QueueOpsApiTest.php`

**Acceptance Criteria:**
- `schedule:list` 中可看到月结、风控扫描、API 落库等定时任务。
- 队列中心接口返回真实 `queue_job_logs`、延迟队列、死信队列数据。

### 阶段 5 问题记录

| 日期 | 阶段 | 状态 | 问题 | 处理记录 |
|------|------|------|------|----------|
| 2026-07-05 | 5-1 | resolved | 队列与账单属于关键链路，直接实现 Job 容易在公式、幂等和日志边界上产生偏差。 | 已先补 `docs/architecture/billing-design.md` 与 `docs/architecture/queue-design.md`，后续 5-1 代码按文档推进。 |
| 2026-07-05 | 5-1 | open | API 超额费单价暂按 MVP 规则 `overage_count * 0.001` 计算，套餐内 API 费用为 0。 | 已在 `BillSettlementService::API_OVERAGE_UNIT_PRICE` 固化为常量并覆盖测试；后续如需按套餐差异化计费，再扩展套餐字段或配置项。 |
| 2026-07-05 | 5-2 | open | `RiskAlertType` 当前只有 4 个枚举值，但阶段 5-2 需要 5 条规则。 | 先用现有 4 类承载 5 条内置规则，其中账单差异映射为 `duplicate_payment`；后续如需更细维度再扩展枚举。 |

- [x] **Step 1: 完成阶段 5-1：账单闭环与核心异步 Job**

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

Result: 2026-07-05 已通过 `php artisan test tests/Unit/Domain/Billing/BillSettlementServiceTest.php tests/Feature/Jobs/MonthlyBillingJobTest.php tests/Feature/Jobs/CoreJobsTest.php`、`php artisan test`、`php artisan migrate:fresh --seed`、`pnpm build`。

- [x] **Step 2: 完成阶段 5-2：风控规则引擎与扫描 Job**

Run:

```bash
php artisan test tests/Unit/Domain/Risk/RuleEngineTest.php tests/Feature/Jobs/RiskRuleScanJobTest.php
```

Result: 2026-07-05 已通过 `php artisan test tests/Unit/Domain/Risk/RuleEngineTest.php tests/Feature/Jobs/RiskRuleScanJobTest.php`、`php artisan test`。

- [x] **Step 3: 完成阶段 5-3：调度注册与任务中心内部接口**

Run:

```bash
php artisan schedule:list
```

```bash
php artisan test tests/Feature/Internal/QueueOpsApiTest.php
```

Result: 2026-07-05 已通过 `php artisan schedule:list`、`php artisan test tests/Feature/Internal/QueueOpsApiTest.php`、`php artisan test tests/Feature/Jobs/ScheduleRegistrationTest.php`。

- [x] **Step 4: 阶段 5 整体验收**

Run:

```bash
php artisan test tests/Feature/Jobs tests/Feature/Internal/QueueOpsApiTest.php
```

Result: 2026-07-05 已通过 `php artisan test`、`php artisan migrate:fresh --seed`、`pnpm build`。

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

### 阶段 6-1：内部 API 契约

**目标：** 先固定 4 个面板的数据接口，确保后续 Vue 只负责展示。

**Files:**
- Create: `docs/architecture/vue-panels-design.md`
- Create: `app/Http/Controllers/Internal/PlatformDashboardController.php`
- Create: `app/Http/Controllers/Internal/ApiMonitoringController.php`
- Create/Modify: `app/Http/Controllers/Internal/QueueOpsController.php`
- Create: `app/Http/Controllers/Internal/RiskReconciliationController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Internal/PlatformDashboardApiTest.php`
- Test: `tests/Feature/Internal/ApiMonitoringApiTest.php`
- Test: `tests/Feature/Internal/RiskReconciliationApiTest.php`

**Acceptance Criteria:**
- `/api/internal/platform/dashboard` 返回 GMV 趋势、套餐占比、队列健康。
- `/api/internal/platform/api-monitor` 返回 24h 调用趋势、Top10、实时日志。
- `/api/internal/platform/queue-ops` 返回队列状态、最近任务、延迟/死信摘要。
- `/api/internal/platform/risk-recon` 返回 7 天风控趋势和差异单列表。

### 阶段 6-2：Vue3 + Echarts 面板

**目标：** 实现 4 个 Vue 面板和 Vite entry，通过 `pnpm build` 验收。

**Files:**
- Create: `resources/js/panels/PlatformDashboardPanel.vue`
- Create: `resources/js/panels/ApiMonitoringPanel.vue`
- Create: `resources/js/panels/QueueOpsPanel.vue`
- Create: `resources/js/panels/RiskReconciliationPanel.vue`
- Create: `resources/js/panels/platform-dashboard.js`
- Create: `resources/js/panels/api-monitoring.js`
- Create: `resources/js/panels/queue-ops.js`
- Create: `resources/js/panels/risk-reconciliation.js`
- Modify: `vite.config.js`

**Acceptance Criteria:**
- 4 个入口均可构建。
- 图表容器尺寸稳定，不依赖页面说明文本。

### 阶段 6-3：Filament 页面挂载

**目标：** 新增 4 个平台后台页面，挂载对应 Vue 面板。

**Files:**
- Create: `resources/views/filament/pages/vue-panel.blade.php`
- Create: `app/Filament/Platform/Pages/PlatformDashboardPage.php`
- Create: `app/Filament/Platform/Pages/ApiMonitoringPage.php`
- Create: `app/Filament/Platform/Pages/QueueOpsPage.php`
- Create: `app/Filament/Platform/Pages/RiskReconciliationPage.php`
- Test: Platform page mount tests

**Acceptance Criteria:**
- 4 个页面在 Filament 平台后台可访问。
- 每个页面响应包含对应挂载节点和 Vite entry。

### 阶段 6 问题记录

| 日期 | 阶段 | 状态 | 问题 | 处理记录 |
|------|------|------|------|----------|
| 2026-07-05 | 6-1 | resolved | 阶段 6 同时涉及内部 API、Vue 构建与 Filament 页面，直接整包实现风险较高。 | 已拆分为 6-1/6-2/6-3，并新增 `docs/architecture/vue-panels-design.md` 固定接口和挂载边界。 |
| 2026-07-05 | 6-2 | open | `pnpm build` 提示 Echarts 公共 chunk 超过 500kB。 | 构建成功，不阻塞阶段验收；如后续要优化首屏体积，可在 Vite 增加 manualChunks 或按页面动态加载 Echarts。 |

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

- [x] **Step 1: 完成阶段 6-1：内部 API 契约**

Run:

```bash
php artisan test tests/Feature/Internal
```

Result: 2026-07-05 已通过 `php artisan test tests/Feature/Internal/PlatformDashboardApiTest.php tests/Feature/Internal/ApiMonitoringApiTest.php tests/Feature/Internal/RiskReconciliationApiTest.php tests/Feature/Internal/QueueOpsApiTest.php`。

- [x] **Step 2: 完成阶段 6-2：Vue3 + Echarts 面板**

Run:

```bash
pnpm build
```

Result: 2026-07-05 已通过 `pnpm build`。

- [x] **Step 3: 完成阶段 6-3：Filament 页面挂载**

Run:

```bash
php artisan test tests/Feature/Filament/Platform/VuePanelPageTest.php
```

Result: 2026-07-05 已通过 `php artisan test tests/Feature/Filament/Platform/VuePanelPageTest.php`。

- [x] **Step 4: 阶段 6 验收标准**

**Acceptance Criteria:**
- 平台仪表盘可展示 GMV 趋势、套餐占比、队列健康
- API 监控页可展示 24h 调用趋势、Top10、实时日志
- 队列中心可展示任务状态环图与任务列表
- 风控对账页可展示 7 天风控命中趋势和差异单表格
- 4 个页面都能在 Filament 中正常挂载，前后端数据字段一致

Result: 2026-07-05 已通过 `php artisan test`、`php artisan migrate:fresh --seed`、`pnpm build`。

---

## 阶段 7：本地压测、单元测试、文档完善

**时间盒：** 第 4 周  
**目标：** 封板前集中做性能、测试与交付材料，确保项目不仅能跑，还能稳定演示、便于答辩和面试说明。

### 阶段 7-1：Smoke / Integration / Performance 测试封板

**状态：** 已完成（2026-07-05）

**目标：** 补齐平台后台、开放 API、核心限流链路的最终验收测试。

**Files:**
- Create: `tests/Feature/Smoke/PlatformSmokeTest.php`
- Create: `tests/Feature/Smoke/ApiSmokeTest.php`
- Create: `tests/Performance/ApiRateLimitBenchTest.php`

**Acceptance Criteria:**
- 平台后台核心页面可访问。
- 开放 API Auth / Products / Orders / Bills / Dashboard 主链路可跑通。
- API 限流核心路径有可重复执行的性能 baseline 测试。

**Result:**
- 新增 `tests/Feature/Smoke/PlatformSmokeTest.php`、`tests/Feature/Smoke/ApiSmokeTest.php`、`tests/Performance/ApiRateLimitBenchTest.php`。
- 已执行 `php artisan test tests/Feature/Smoke tests/Performance/ApiRateLimitBenchTest.php`，10 passed。
- Baseline：25 次 `/api/v1/products` 请求平均 4.67-6.33ms。

### 阶段 7-2：本地压测与运行文档

**状态：** 已完成（2026-07-05）

**目标：** 写清楚本地启动、测试、构建、Octane 压测步骤，沉淀可复现 benchmark 记录。

**Files:**
- Modify: `README.md`
- Create: `docs/testing/local-benchmark.md`

**Acceptance Criteria:**
- README 包含 `pnpm` 前端命令、迁移种子、测试、构建、后台账号、开放 API 示例。
- benchmark 文档包含 Octane、Redis、数据库准备与压测目标路径。

**Result:**
- 更新 `README.md`。
- 新增 `docs/testing/local-benchmark.md`。
- 已记录 Octane 压测步骤与本地 Pest baseline。

### 阶段 7-3：API/架构文档收口

**状态：** 已完成（2026-07-05）

**目标：** 补内部接口 OpenAPI，并扫清交付文档中的占位词。

**Files:**
- Create: `docs/api/internal.yaml`
- Modify: `docs/architecture/layered-architecture.md`
- Modify: `docs/database/schema-overview.md`
- Modify: `docs/api/openapi.yaml`

**Acceptance Criteria:**
- `docs/api/internal.yaml` 描述 4 个平台内部面板接口。
- 对外交付文档无明显占位词。

**Result:**
- 新增 `docs/api/internal.yaml`。
- 更新 `docs/architecture/layered-architecture.md`、`docs/database/schema-overview.md`、`docs/api/openapi.yaml`。
- 修正旧计划文档中的前端安装命令为 `pnpm add`。

### 阶段 7 问题记录

| 日期 | 阶段 | 状态 | 问题 | 处理记录 |
|------|------|------|------|----------|
| 2026-07-05 | 7-2 | 已记录 | 当前本机 `php -m` 未列出 Swoole 扩展，`php artisan octane:status` 显示 Octane 未运行；未做长时间 HTTP 压测。 | 先以 `tests/Performance/ApiRateLimitBenchTest.php` 提供可重复 baseline，并在 `docs/testing/local-benchmark.md` 写明 Octane/Swoole 实机压测步骤；后续安装 Swoole 后可按模板补真实压测记录。 |
| 2026-07-05 | 7-2 | 已记录 | `pnpm build` 成功，但 Vite 提示 `panel-app` chunk 超过 500KB。 | 不阻塞阶段 7 验收；来源为 Vue/Echarts 面板公共包。后续如需优化，可拆分动态 import 或配置 manualChunks。 |

---

## 阶段 8：封板后优化与实机验收

**时间盒：** 交付前增强  
**目标：** 在阶段 7 已可交付的基础上，补齐实机压测、前端构建优化与最终交付核对。

### 阶段 8-1：Octane / 本地 HTTP 压测链路

**状态：** 已完成（2026-07-05）

**目标：** 提供一个可重复执行的本地 HTTP benchmark 命令，并沉淀 Octane 实机压测设计。

**Files:**
- Create: `app/Console/Commands/LocalApiBenchmarkCommand.php`
- Create: `tests/Feature/Console/LocalApiBenchmarkCommandTest.php`
- Create: `docs/architecture/local-benchmark-design.md`
- Modify: `docs/testing/local-benchmark.md`

**Acceptance Criteria:**
- 可执行 `php artisan benchmark:local-api` 对本地服务压测。
- 命令支持 `artisan serve` 与 Octane 同一入口。
- 输出平均耗时、P95、错误率。
- 本机无法运行 Octane 时，问题记录清楚，不阻塞命令和文档交付。

**Result:**
- 新增 `benchmark:local-api` 命令。
- 新增命令级测试，使用 `Http::fake()` 覆盖成功与鉴权失败路径。
- 新增 `docs/architecture/local-benchmark-design.md`。

### 阶段 8 问题记录

| 日期 | 阶段 | 状态 | 问题 | 处理记录 |
|------|------|------|------|----------|
| 2026-07-05 | 8-1 | 已记录 | 当前本机未安装 Swoole，无法启动 Octane 做实机 HTTP 压测。 | 先交付 `benchmark:local-api` 命令与设计文档；后续安装 Swoole 后直接按文档补 Octane 实测记录。 |

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
