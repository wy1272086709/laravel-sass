# 分层架构说明

多商户 SaaS 电商开放中台采用**五层架构**，自顶向下职责清晰、依赖单向。

---

## 架构总览

```
┌─────────────────────────────────────────────────────────────────┐
│                     规范层 (Specification Layer)                 │
│                                                                   │
│  docs/api/openapi.yaml        OpenAPI 3.0 对外接口契约            │
│  docs/api/internal.yaml       平台内部面板接口契约                │
│  app/Domain/*/Enums/          PHP 8.3 Backed Enum 枚举契约         │
│  app/Domain/*/DTO/            数据传输对象（请求/响应）            │
│  config/permissions.php       权限矩阵（平台角色 × 权限点）        │
│  docs/database/schema-overview.md  数据表结构规范                  │
└────────────────────────────┬────────────────────────────────────┘
                             │ 契约约束
┌────────────────────────────▼────────────────────────────────────┐
│              Filament 后台层 (Admin Presentation Layer)          │
│                                                                   │
│  app/Filament/Platform/         PlatformPanel (Guard: platform)   │
│    ├── Resources/               TenantResource, PackageResource…  │
│    ├── Pages/                   4 个 Vue3 面板页                  │
│    └── Widgets/                 StatsOverview                     │
│                                                                   │
│  app/Filament/Merchant/         MerchantPanel (Guard: merchant) │
│    ├── Resources/               ProductResource, OrderResource…   │
│    └── Pages/                   MerchantDashboardPage             │
│                                                                   │
│  resources/js/panels/           4 个 Vue3 Echarts 嵌入组件        │
│  app/Http/Controllers/Internal/ 平台内部面板 JSON 接口             │
│  app/Http/Controllers/          ImpersonationController          │
│                                                                   │
│  职责：UI 渲染、表单校验、表格交互、权限菜单、系统切换              │
│  禁止：直接操作 Redis / 写裸 SQL / 绕过 TenantContext             │
└────────────────────────────┬────────────────────────────────────┘
                             │ 调用用例服务
┌────────────────────────────▼────────────────────────────────────┐
│           Octane 服务层 (Application + Domain Layer)           │
│                                                                   │
│  app/Domain/                    领域模型 + 业务规则                 │
│    ├── Tenant/TenantContext     readonly 租户上下文               │
│    ├── Order/OrderStateMachine  订单状态机                        │
│    ├── Risk/RuleEngine          轻量风控规则引擎                   │
│    └── Billing/BillSettlement   账单结算（A 状态流转）             │
│                                                                   │
│  app/Application/               用例编排（无框架依赖）              │
│    ├── Platform/                平台管理用例                       │
│    ├── Merchant/                商户经营用例                       │
│    └── Api/                     开放 API 用例                       │
│                                                                   │
│  app/Http/Middleware/           请求生命周期中间件                 │
│    ├── ResolveTenantContext                                     │
│    ├── ApplyTenantGlobalScope                                   │
│    ├── ApiRateLimitMiddleware                                   │
│    ├── SqlTenantGuard                                           │
│    └── OctaneTenantCleanupMiddleware                            │
│                                                                   │
│  职责：业务逻辑、状态流转、规则命中、租户上下文管理                  │
│  运行：Laravel Octane (Swoole) 常驻进程                           │
└────────────────────────────┬────────────────────────────────────┘
                             │ 调用基础设施
┌────────────────────────────▼────────────────────────────────────┐
│            Redis 分布式层 (Infrastructure — Redis)               │
│                                                                   │
│  app/Infrastructure/Redis/                                        │
│    ├── LuaDistributedLock.php     原子分布式锁                     │
│    ├── SlidingWindowRateLimiter.php  滑动窗口 API 限流             │
│    ├── DelayQueue.php             延迟队列（订单超时）              │
│    ├── DeadLetterQueue.php        死信队列（3 次重试失败）          │
│    └── ApiDailyCounter.php        日 API 调用计数                  │
│                                                                   │
│  Key 命名规范：saas:{tenant_id}:{module}:{identifier}            │
│  职责：高性能计数、限流、锁、异步调度                               │
└────────────────────────────┬────────────────────────────────────┘
                             │ 持久化
┌────────────────────────────▼────────────────────────────────────┐
│            MySQL 持久层 (Persistence Layer)                      │
│                                                                   │
│  database/migrations/           26 张表迁移                        │
│  app/Models/ + TenantScope      Eloquent + 全局 tenant_id 过滤    │
│  database/factories/            测试数据工厂                       │
│  database/seeders/              演示种子数据                       │
│                                                                   │
│  共享库多租户：所有租户域表含 tenant_id，平台域表无 tenant_id       │
│  SqlTenantGuard 拦截无 tenant_id 条件的 SELECT/UPDATE/DELETE     │
└─────────────────────────────────────────────────────────────────┘
```

---

## 层间依赖规则

| 规则 | 说明 |
|------|------|
| 单向依赖 | 上层可调用下层，下层不可感知上层 |
| 规范层无代码依赖 | Enum/DTO/OpenAPI 被各层引用，自身不依赖框架 |
| Filament 不直连 Redis | 必须通过 Application 服务层 |
| Domain 不依赖 Filament/Http | 纯 PHP，可独立单元测试 |
| Infrastructure 可替换 | Redis 实现可 Mock，接口面向 Domain |

---

## 请求生命周期（开放 API）

```
1. HTTP Request → /api/v1/orders
2. ApiAuthMiddleware
   └─ 解析 Bearer AccessToken → 查找 api_keys → 注入 TenantContext
3. ApiRateLimitMiddleware
   └─ Redis SlidingWindow 检查日配额 → 分级策略（basic 硬阻断 / pro 软告警）
4. SqlTenantGuard（注册全局监听）
5. OrderController → OrderQueryService → Order Model (TenantScope 自动过滤)
6. OctaneTenantCleanupMiddleware
   └─ 清理 TenantContext 单例、静态变量
7. HTTP Response
```

---

## 请求生命周期（Filament 后台）

```
1. HTTP Request → /platform/tenants
2. Filament Auth Middleware (Guard: platform)
3. ResolveTenantContext
   └─ platform Guard → tenantId = null（平台全局视图）
4. Filament TenantResource → Tenant Model（无 TenantScope，平台可查全量）
5. OctaneTenantCleanupMiddleware
6. Response
```

## 请求生命周期（平台 Vue 面板）

```
1. HTTP Request → /platform/api-monitoring
2. Filament Auth Middleware (Guard: platform)
3. Blade 壳加载 Vite entry → resources/js/panels/api-monitoring.js
4. Vue 组件请求 /api/internal/platform/api-monitor
5. Internal Controller 聚合 MySQL / Redis / Queue 数据
6. Echarts 渲染趋势、分布、列表
```

内部面板接口：
- `GET /api/internal/platform/dashboard`
- `GET /api/internal/platform/api-monitor`
- `GET /api/internal/platform/queue-ops`
- `GET /api/internal/platform/risk-recon`

这些接口只走平台后台 session，不对第三方开放。契约见 `docs/api/internal.yaml`。

---

## Impersonation 流程

```
平台管理员点击「进入商户后台」
  │
  ├─ ImpersonationController::start(tenantId)
  │    ├─ 创建 impersonation_logs 记录
  │    ├─ 签发 MerchantSessionToken（含 impersonated_by = platform_user_id）
  │    └─ TenantContext(tenantId, impersonatorId, tier)
  │
  ├─ 重定向到 MerchantPanel Dashboard
  │
  └─ 商户 Sidebar 显示「返回平台总后台」
       │
       ImpersonationController::stop()
         ├─ 更新 impersonation_logs.ended_at
         ├─ 销毁 MerchantSessionToken
         └─ 恢复 PlatformSessionToken
```

---

## Octane 租户串号防护

Swoole 常驻进程下，静态变量和单例在请求间共享。防护措施：

| 措施 | 实现 |
|------|------|
| readonly TenantContext | 请求级绑定，不存静态属性 |
| OctaneTenantCleanupMiddleware | 请求结束 `app()->forgetInstance(TenantContext::class)` |
| TenantScope 动态注册 | 每次请求重新绑定 tenant_id 到 Scope |
| Eloquent 模型 `$guarded` | 禁止批量赋值 tenant_id |
| SqlTenantGuard | `DB::listen()` 检测无 tenant_id 的 UPDATE/DELETE |

---

## 模块边界

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Platform   │     │   Merchant   │     │  Open API    │
│   平台管理    │     │   商户经营    │     │  开放网关     │
├──────────────┤     ├──────────────┤     ├──────────────┤
│ Tenant管理   │     │ Product CRUD │     │ Auth Token   │
│ Package配置  │     │ Order管理    │     │ Product API  │
│ 角色权限     │     │ Coupon营销   │     │ Order API    │
│ 4×Vue3面板   │     │ ApiKey管理   │     │ Bill API     │
│ Impersonation│     │ 月度账单     │     │ Dashboard API│
└──────┬───────┘     └──────┬───────┘     └──────┬───────┘
       │                    │                    │
       └────────────────────┼────────────────────┘
                            │
              ┌─────────────▼─────────────┐
              │     Shared Domain Core     │
              │  TenantContext · Billing   │
              │  Risk · Queue · ApiKey     │
              └───────────────────────────┘
```

---

## 技术亮点（面试向）

1. **readonly TenantContext + Octane 清理中间件** — Swoole 常驻下零租户串号
2. **Redis Lua 原子锁 + 滑动窗口** — 分布式限流与月结互斥
3. **SqlTenantGuard** — 编译期拦截裸 SQL 越权
4. **双 Token 完全隔离** — 后台 Session 与开放 API AccessToken 独立生命周期
5. **轻量规则引擎** — 可配置阈值 + 自动告警，无重型规则框架
6. **月结半自动对账** — 系统算应收 + 人工填实报 + 自动差异单
7. **Impersonation 审计链** — 平台→商户上下文切换全程留痕
