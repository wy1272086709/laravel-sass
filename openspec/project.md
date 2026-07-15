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
- 商品支持 SPU + 多 SKU 交互；风控为 5 条轻量可配置规则。

## 实现现状

当前已完成阶段 1-8 的 MVP 功能开发，进入本地环境验收和交付收口阶段。

- ✅ 阶段 A（项目骨架）：Laravel 11 + Filament v3 双 Panel（`/platform`、`/merchant`）、双 Guard、统一登录和目录骨架已落地。
- ✅ 阶段 1（架构基座）：16 个 Enum、`TenantContext` / `TenantScope` / `BelongsToTenant`、Redis 基础工具、配额策略和租户中间件链已实现。
- ✅ 阶段 2（数据层）：26 个迁移文件、核心 Eloquent 模型、Factory、Seeder 和多租户自动过滤已实现；演示账号及基础经营数据可生成。
- ✅ 阶段 3（双后台）：平台端商户、套餐、角色权限管理，以及商户端商品、订单、优惠券、API Key、账单和经营概览已实现；支持平台管理员 Impersonation。
- ✅ 阶段 4（开放 API）：Access/Refresh Token、统一错误信封、Products、Orders、Bills、Dashboard、权限校验和分级配额限流已实现，统一前缀为 `/api/v1`。
- ✅ 阶段 5（任务与业务闭环）：月结账单、对账差异、5 条风控规则、API 用量落库、订单关闭等核心 Job、调度注册及队列运维内部接口已实现。
- ✅ 阶段 6（可视化）：平台仪表盘、API 监控、队列运维、风控对账 4 个 Vue3 + Echarts 面板及其内部 API、Filament 页面挂载已实现。
- ✅ 阶段 7（测试与文档）：Smoke、Integration、限流 baseline 测试，以及对外/内部 OpenAPI、架构、数据库、本地压测和启动文档已补齐。
- ✅ 阶段 8（封板后增强）：`benchmark:local-api` 本地 HTTP benchmark 命令、命令测试和压测设计文档已实现。

## 当前验收状态

- `pnpm build` 已通过；`panel-app` 产物约 630 KB，仍有 Vite 大 chunk 警告，后续可通过动态加载或 `manualChunks` 优化。
- 2026-07-14 本机执行全量测试结果为 81 passed、39 failed。失败主要由本机 Redis 开启认证但测试环境未提供正确 `REDIS_PASSWORD` 导致（`NOAUTH Authentication required`），需要修正环境配置后重新全量验收。
- 本机尚未安装 Swoole，因此 Octane 实机运行和长时间 HTTP 压测尚未完成；当前仅有 Pest baseline 和可复用的本地 benchmark 命令。
- 当前定位为功能已铺齐的可演示 MVP；达到稳定交付状态前，还需完成 Redis 测试环境修复、全量测试转绿、Octane/Swoole 实测和前端包体优化。

## 本期范围边界

- 账单仅实现状态流转和支付字段预留，不对接真实支付渠道。
- 商品支持后台及开放 API 多 SKU 维护，下单时记录 SKU 与规格快照并扣减 SKU 库存。
- 风控为 5 条轻量可配置规则，不包含复杂实时风控平台能力。
