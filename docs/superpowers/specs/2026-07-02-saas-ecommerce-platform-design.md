# 多商户 SaaS 电商开放中台 — 软件设计文档（SDD）

| 属性 | 值 |
|------|-----|
| 版本 | v1.0.0 |
| 日期 | 2026-07-02 |
| 状态 | 已评审确认 |
| 技术栈 | PHP 8.3 · Laravel 11 · Filament v3 · Vue3 · Octane/Swoole · Redis · MySQL 8 |

---

## 1. 项目概述

### 1.1 目标

构建一套**多商户 SaaS 电商开放中台**，包含：

- **平台管理后台**：商户入驻、套餐配置、API 监控、队列运维、风控对账、角色权限
- **商户管理后台**：店铺经营、商品/订单/营销、API 密钥、月度账单
- **开放 API 网关**：`/api/v1` 统一前缀，双 Token 鉴权，供第三方 ERP/系统对接

### 1.2 已锁定架构决策

| # | 议题 | 决策 |
|---|------|------|
| 1 | 整体架构 | 方案 1：模块化单体 |
| 2 | 账单结算 | A 状态流转为主 + C 支付字段预留 |
| 3 | 账号体系 | A 双表独立（`platform_users` / `merchant_users`）+ Impersonation |
| 4 | API 配额 | C 分级策略（基础硬阻断 / 专业企业软告警+超额 / 150% 全局硬阻断） |
| 5 | 商品模型 | C 本期单 SKU 交互，底层 SPU+SKU 全预留 |
| 6 | 风控规则 | B 轻量规则引擎（3～5 条可配置规则） |
| 7 | 对账差异 | B 月结半自动生成差异单 |

### 1.3 强制技术约束映射

| 约束 | 实现方案 |
|------|----------|
| PHP 8.3 Enum + readonly TenantContext | `app/Domain/Tenant/TenantContext.php` |
| Filament v3 为主 + 4 个 Vue3 面板 | 见 §4 页面清单 |
| Laravel Octane Swoole + 内存清理 | `OctaneTenantCleanupMiddleware` |
| Redis Lua 锁 / 延迟队列 / 死信 / 滑动窗口限流 | `app/Infrastructure/Redis/*` |
| 共享库多租户 + tenant_id 全局过滤 | `TenantScope` + `SqlTenantGuard` |
| 商户独立收款，仅经营对账 | 账单状态流转，不对接支付渠道 |
| `/api/v1` + 双 Token | Platform/Merchant Session Token + AccessToken |

---

## 2. 系统架构

### 2.1 五层架构

```
规范层 (Specification)
  OpenAPI 3.0 · Enum 契约 · DTO · 权限矩阵
        │
Filament 后台层 (Admin Presentation)
  PlatformPanel · MerchantPanel · 4×Vue3 Panel · Impersonation
        │
Octane 服务层 (Application + Domain)
  TenantContext · 用例服务 · 状态机 · 规则引擎 · 中间件链
        │
Redis 分布式层 (Infrastructure)
  Lua 原子锁 · 滑动窗口限流 · 延迟/死信队列 · API 日计数
        │
MySQL 持久层 (Shared-DB Multi-Tenant)
  全局 TenantScope · 平台表无 tenant_id · 审计日志
```

详见 `docs/architecture/layered-architecture.md`。

### 2.2 双 Token 鉴权

| 入口 | Token | Guard / 机制 |
|------|-------|-------------|
| 平台后台 | `PlatformSessionToken` | `platform` Guard + Sanctum |
| 商户后台 | `MerchantSessionToken` | `merchant` Guard + Sanctum |
| Impersonation | 商户 Token + `impersonated_by` | 中间件注入 TenantContext |
| 开放 API | `AccessToken`（app_key + app_secret 换取） | `ApiAuthMiddleware`，与后台完全隔离 |

### 2.3 TenantContext（readonly）

```php
readonly class TenantContext
{
    public function __construct(
        public ?int $tenantId,
        public ?int $impersonatorId,
        public PackageTier $tier,
    ) {}
}
```

### 2.4 Octane 中间件链

```
Request
  → ResolveTenantContext
  → ApplyTenantGlobalScope
  → ApiRateLimitMiddleware       // Redis 滑动窗口，分级策略
  → SqlTenantGuard               // 拦截无 tenant_id 裸 SQL
  → [业务处理]
  → OctaneTenantCleanupMiddleware
Response
```

### 2.5 项目目录结构

```
laravelProj/
├── app/
│   ├── Domain/
│   │   ├── Tenant/          TenantContext, TenantScope, PackageTier
│   │   ├── Product/         Product, ProductSku, ProductStatus
│   │   ├── Order/           Order, OrderItem, OrderStateMachine
│   │   ├── Billing/         TenantBill, BillSettlementService
│   │   ├── Risk/            RiskRule, RiskAlert, RuleEngine
│   │   └── Api/             ApiKey, ApiPermission, RateLimiter
│   ├── Application/
│   │   ├── Platform/        平台用例服务
│   │   ├── Merchant/        商户用例服务
│   │   └── Api/             开放 API 用例服务
│   ├── Infrastructure/
│   │   ├── Redis/           LuaLock, SlidingWindow, DelayQueue, DeadLetter
│   │   └── Octane/          TenantCleanup, SqlGuard
│   ├── Filament/
│   │   ├── Platform/        PlatformPanel + Resources
│   │   └── Merchant/        MerchantPanel + Resources
│   └── Http/
│       ├── Api/V1/          开放网关 Controller
│       ├── Internal/        Vue3 面板内部 API
│       └── Middleware/
├── resources/js/panels/     4 个 Vue3 Echarts 面板
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
└── docs/                    本文档及配套规范
```

---

## 3. 数据模型

### 3.1 Enum 清单

| Enum | 值 |
|------|-----|
| `PackageTier` | `basic`, `professional`, `enterprise` |
| `TenantStatus` | `enabled`, `disabled` |
| `ProductStatus` | `listed`, `unlisted` |
| `OrderStatus` | `pending_payment`, `paid`, `shipped`, `completed`, `refund_requested`, `cancelled` |
| `CouponType` | `full_reduction`, `discount` |
| `CouponStatus` | `not_started`, `active`, `ended` |
| `ApiKeyStatus` | `enabled`, `disabled` |
| `ApiPermission` | `product_query`, `order_manage`, `dashboard_read`, `bill_query` |
| `BillStatus` | `pending_settlement`, `settled`, `overdue` |
| `RiskLevel` | `high`, `medium`, `low` |
| `RiskAlertType` | `brush_order`, `duplicate_payment`, `abnormal_login`, `high_refund_rate` |
| `RiskAlertStatus` | `pending`, `handled`, `ignored` |
| `ReconciliationStatus` | `unreconciled`, `reconciled` |
| `PackageChangeType` | `new_purchase`, `upgrade`, `downgrade` |
| `QueueJobStatus` | `pending`, `processing`, `success`, `failed`, `dead` |
| `LoginResult` | `success`, `failure` |

### 3.2 核心表（28 张）

详见 `docs/database/schema-overview.md`。

**平台域**（无 tenant_id Scope）：`platform_users`, `platform_roles`, `platform_permissions`, `platform_role_permission`, `packages`, `package_change_logs`, `risk_rules`, `login_logs`

**租户域**（强制 TenantScope）：`tenants`, `merchant_users`, `tenant_settings`, `products`, `product_skus`, `orders`, `order_items`, `coupons`, `api_keys`, `api_request_logs`, `api_usage_daily`, `tenant_bills`, `reconciliation_discrepancies`, `risk_alerts`, `queue_job_logs`, `impersonation_logs`

**系统域**：`personal_access_tokens`, `failed_jobs`, `migrations`

### 3.3 订单状态机

```
pending_payment ──► paid ──► shipped ──► completed
       │               │
       │               └──► refund_requested ──► completed / cancelled
       └──► cancelled (超时 Job / 手动)
```

### 3.4 月结半自动对账流程

```
MonthlyBillingJob（每月 1 日 02:00）
  │
  ├─ 汇总 tenant 上月订单交易总额
  ├─ 计算 commission = total × commission_rate（默认 2%）
  ├─ 汇总 api_usage_fee + api_overage_fee
  ├─ 生成 tenant_bills（status = pending_settlement）
  │
  └─ platform_receivable = commission + api_fees（系统自动）
       merchant_reported_amount = NULL（待运营填写）
       │
       运营填写 merchant_reported_amount
       │
       difference = platform_receivable - merchant_reported
       │
       if difference ≠ 0 → 自动生成 reconciliation_discrepancies
```

### 3.5 API 配额分级策略

| 套餐 | 配额内 | 超额 | 150% 阈值 |
|------|--------|------|-----------|
| 基础版 | 正常 | **硬阻断 429** | — |
| 专业版/企业版 | 正常 | 软告警 + 超额计费 | **全局硬阻断 429** |

---

## 4. 页面清单

### 4.1 Vue3 嵌入面板（4 个）

| # | 面板 | Filament 壳 | Vue 组件 | 图表 |
|---|------|------------|----------|------|
| 1 | 全平台经营看板 | `Platform/DashboardPage` | `PlatformDashboardPanel.vue` | GMV 趋势、套餐占比、队列健康 |
| 2 | API 全局监控 | `Platform/ApiMonitoringPage` | `ApiMonitoringPanel.vue` | 24h 折线、Top10 横条、实时日志 |
| 3 | 队列调度中心 | `Platform/QueueCenterPage` | `QueueOpsPanel.vue` | 状态环形图、任务列表 |
| 4 | 对账与风控 | `Platform/RiskReconciliationPage` | `RiskReconciliationPanel.vue` | 7 天命中趋势、差异单联动 |

### 4.2 纯 Filament 页面（14 页）

**平台 Panel**

| 页面 | Filament 类型 |
|------|--------------|
| 商户管理 | `TenantResource` |
| 套餐配置 | `PackageResource` + ChangeLog Relation |
| 角色权限 | `PlatformRoleResource` |
| 个人中心 | `PlatformProfilePage` |

**商户 Panel**

| 页面 | Filament 类型 |
|------|--------------|
| 店铺概览 | `MerchantDashboardPage`（Filament Stats + Table） |
| 商品管理 | `ProductResource` |
| 订单管理 | `OrderResource` |
| 营销优惠 | `CouponResource` |
| API 密钥 | `ApiKeyResource`（含行内 Vue 折线图 widget） |
| 月度账单 | `TenantBillResource` |

**共用**：登录页（Tab 切换平台/商户）

### 4.3 系统切换

| 入口 | 行为 |
|------|------|
| 进入商户后台 | Impersonation → 商户 Session |
| 返回平台总后台 | 结束 Impersonation → 恢复平台 Session |

---

## 5. 开放 API

### 5.1 概览

- 前缀：`/api/v1`
- 鉴权：`app_key` + `app_secret` → `AccessToken`（Bearer）
- 规范文件：`docs/api/openapi.yaml`
- 内部接口：`/api/internal/*`（不纳入对外 OpenAPI）

### 5.2 端点分组

| 分组 | 端点数 | 权限依赖 |
|------|--------|----------|
| Auth | 3 | 无 / Token |
| Products | 5 | `product_query` / `order_manage` |
| Orders | 6 | `order_manage` |
| Bills | 3 | `bill_query` |
| Dashboard | 2 | `dashboard_read` |
| Webhooks | 1 | 预留 |

### 5.3 限流响应

```json
{
  "code": 42901,
  "message": "API daily quota exceeded",
  "data": { "tier": "basic", "quota": 10000, "used": 10001 }
}
```

---

## 6. Redis 基础设施

| 组件 | 实现 | 用途 |
|------|------|------|
| `LuaDistributedLock` | `SET key NX PX` + Lua 释放脚本 | 月结任务互斥、防重复结算 |
| `SlidingWindowRateLimiter` | Sorted Set + Lua | API 日配额 / QPS 限流 |
| `DelayQueue` | ZADD + 轮询消费 | 订单超时关闭（30min） |
| `DeadLetterQueue` | List + failed_jobs 联动 | 失败任务 3 次重试后进死信 |
| `ApiDailyCounter` | INCR + EXPIREAT | 实时 API 消耗计数，日终落库 |

---

## 7. 队列任务清单

| Job | 队列 | 触发 | 说明 |
|-----|------|------|------|
| `CloseExpiredOrderJob` | `default`（延迟） | 下单后 30min | 超时未支付自动关闭 |
| `MonthlyBillingJob` | `billing` | 每月 1 日 02:00 | 月结账单生成 |
| `GenerateApiBillJob` | `billing` | 月结子任务 | API 超额费计算 |
| `RiskRuleScanJob` | `default` | 每小时 | 规则引擎扫描 |
| `MerchantWelcomeEmailJob` | `notification` | 商户入驻 | 欢迎邮件 |
| `InventoryAlertJob` | `default` | 库存变更 | 库存预警 |
| `SyncLogisticsJob` | `sync_data` | 发货后 | 第三方物流同步（Mock） |
| `ApiUsageFlushJob` | `default` | 每日 00:05 | Redis 计数落库 `api_usage_daily` |

---

## 8. 风控规则引擎

### 8.1 内置规则（5 条）

| code | 名称 | 条件 | 告警类型 |
|------|------|------|----------|
| `HIGH_FREQ_ORDER` | 高频下单 | 同 IP 10min 内 ≥ 20 单 | `brush_order` |
| `HIGH_REFUND_RATE` | 高退款率 | 7 日退款率 ≥ 5% | `high_refund_rate` |
| `ABNORMAL_LOGIN` | 异地登录 | 登录 IP 省份与上次不同 | `abnormal_login` |
| `DUPLICATE_PAYMENT` | 重复支付 | 同订单号 5min 内重复支付事件 | `duplicate_payment` |
| `API_SPIKE` | API 调用异常 | 1h 调用量超日均 300% | `brush_order` |

### 8.2 处理流

```
RiskRuleScanJob → 遍历 active rules → 查询阈值 → 命中写入 risk_alerts
  → 平台仪表盘/风控面板展示 → 运营「标记处理」/「忽略」
```

---

## 9. 非功能需求

| 维度 | 指标 |
|------|------|
| API 响应 | P99 < 200ms（Octane 常驻） |
| 并发 | 设计目标 12,400 QPS（参考截图） |
| 租户隔离 | 全局 Scope + SqlGuard + Octane 清理，零串号 |
| 安全 | 双 Token 隔离、app_secret HASH 存储、Impersonation 审计 |
| 可观测 | API 日志、队列日志、登录日志、Impersonation 日志 |

---

## 10. 配套文档索引

| 文档 | 路径 |
|------|------|
| 五层架构详述 | `docs/architecture/layered-architecture.md` |
| 数据库结构 / 迁移 / Model / Factory | `docs/database/schema-overview.md` |
| OpenAPI 3.0 规范 | `docs/api/openapi.yaml` |
| 内部 API（Vue3 面板） | `docs/api/internal.yaml`（待建） |

---

## 11. 版本记录

| 版本 | 日期 | 说明 |
|------|------|------|
| v1.0.0 | 2026-07-02 | 初版 SDD，二～四节评审通过 |
