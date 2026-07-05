# 多租户隔离 (tenant-isolation)

## Purpose

在共享库多租户模型下，保证不同商户数据互不可见、平台管理员可查全量，且 Swoole 常驻进程下不发生租户串号。

## Requirements

### Requirement: readonly 租户上下文
系统 SHALL 以 `App\Domain\Tenant\TenantContext`（`readonly`）承载当前请求的租户身份，字段 `tenantId`、`impersonatorId`、`tier`。

#### Scenario: 平台全局视图
- WHEN 当前请求未命中 merchant guard（平台管理员或未登录）
- THEN `TenantContext.tenantId` 为 `null`，`isPlatformView()` 返回 `true`。

#### Scenario: 商户视图
- WHEN merchant guard 命中
- THEN `tenantId` 取自 `merchant_users.tenant_id`，`tier` 取自 `tenant.package.tier`。

#### Scenario: Impersonation
- WHEN 会话含 `impersonated_by`
- THEN `impersonatorId` 为平台管理员 ID，`isImpersonating()` 返回 `true`。

### Requirement: 全局租户过滤
租户域模型 SHALL 通过 `BelongsToTenant` trait 自动注册 `TenantScope`，并在创建时从 `TenantContext` 填充 `tenant_id`。

#### Scenario: 商户视角查询自动过滤
- WHEN `TenantContext.tenantId = 7` 并查询租户域表
- THEN 仅返回 `tenant_id = 7` 的记录。

#### Scenario: 平台视角不过滤
- WHEN `tenantId` 为 `null`
- THEN 查询不附加 `tenant_id` 条件，返回全量。

### Requirement: 请求结束上下文清理
系统 SHALL 在每个 HTTP 请求结束后，经 `OctaneTenantCleanupMiddleware` 的 `finally` 重置 `TenantContext` 为平台全局（tenantId=null），防止常驻进程下请求间串号。

#### Scenario: 残留上下文被重置
- WHEN 上一请求残留 `tenantId=5` 的上下文
- THEN 当前请求处理结束后 `app(TenantContext::class)->tenantId` 为 `null`。

#### Scenario: 异常路径仍清理
- WHEN 下游中间件/控制器抛出异常
- THEN 上下文清理 STILL 执行（`finally`）。

### Requirement: 裸 SQL 越权守卫
系统 SHALL 通过 `SqlTenantGuard`（`DB::listen`）检测对 tenant 域表的 UPDATE/DELETE 缺少 `tenant_id` 条件：默认记录告警，严格模式下抛异常。

## Status

- ✅ readonly 上下文、TenantScope、BelongsToTenant、OctaneTenantCleanupMiddleware、SqlTenantGuard、ResolveTenantContext（阶段 1）
- ⏳ merchant_users 关系落地、`tenant.package.tier` 真实解析（阶段 2）
