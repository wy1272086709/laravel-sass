# 多商户 SaaS 电商开放中台

一套**多商户 SaaS 电商开放中台**，以模块化单体（Laravel 11）承载：平台管理后台（商户入驻、套餐、API 监控、队列运维、风控对账、角色权限）、商户管理后台（店铺经营、商品/订单/营销、API 密钥、月度账单），以及 `/api/v1` 开放网关（双 Token 鉴权，供第三方 ERP/系统对接）。

权威设计文档：
- SDD：[docs/superpowers/specs/2026-07-02-saas-ecommerce-platform-design.md](../docs/superpowers/specs/2026-07-02-saas-ecommerce-platform-design.md)
- 五层架构：[docs/architecture/layered-architecture.md](../docs/architecture/layered-architecture.md)
- 数据库：[docs/database/schema-overview.md](../docs/database/schema-overview.md)
- OpenAPI：[docs/api/openapi.yaml](../docs/api/openapi.yaml)

## 技术栈

- PHP 8.3（运行时 8.4 兼容）· Laravel 11 · Filament v3（双 Panel）
- Laravel Octane（Swoole，运行时延后）· Redis 7（predis 客户端）
- MySQL 8（共享库多租户）· Sanctum · Vue3 + Echarts（4 个嵌入面板）· Pest PHP

## 核心原则

- **五层单向依赖**：规范层 → Filament 层 → 应用/领域层 → Redis 基础设施 → MySQL 持久层；上层调用下层，下层不可感知上层（见 [agreements/layered-architecture.md](agreements/layered-architecture.md)）。
- **多租户隔离零串号**：`readonly TenantContext` 请求级绑定 + `TenantScope` 全局过滤 + `SqlTenantGuard` 拦截裸 SQL + `OctaneTenantCleanupMiddleware` 请求结束重置。
- **双 Token 完全隔离**：后台 Session（platform/merchant guard）与开放 API AccessToken 独立生命周期。
- **契约先行**：OpenAPI 3.0 + PHP 8.3 backed Enum 为对外/对内契约，先于实现冻结。

## 关键约束

- 平台域表无 `tenant_id`（`platform_users`/`packages`/`risk_rules` 等）；租户域表强制 `tenant_id` 全局 Scope（16 张表）。
- 账单仅状态流转（A 方案），`payment_channel` 等字段为 C 预留，不对接支付渠道。
- API 配额分级：基础版硬阻断；专业/企业版软告警+超额计费；150% 全局硬阻断。
- Octane/Swoole 常驻下，静态/单例在请求间共享——所有请求级状态必须经 `forgetInstance` 清理。
- 本期单 SKU 交互，`product_skus` 表预留；风控为 5 条轻量可配置规则。

## 实现现状

- ✅ 阶段 A 搭架子：Laravel 11 + Filament v3 双 Panel（`/platform`、`/merchant`）可登录、双 Guard、目录骨架、SQLite/predis 配置。
- ✅ 阶段 1 基座：16 Enum、`TenantContext`/`TenantScope`/`BelongsToTenant`、5 Redis 工具、`QuotaPolicyService`、中间件链（Resolve/Apply/SqlGuard/RateLimit/Cleanup）、34 项 Pest 测试全绿。
- ⏳ 阶段 2-7：迁移/模型、Filament CRUD、API 网关、队列/月结、Vue3 面板、压测/文档（延后）。
