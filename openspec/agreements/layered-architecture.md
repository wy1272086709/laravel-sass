# Agreement: 分层架构与依赖规则

> 来源：[docs/architecture/layered-architecture.md](../../docs/architecture/layered-architecture.md)

## 五层结构（自顶向下）

1. **规范层**：OpenAPI 3.0、PHP 8.3 backed Enum、DTO、`config/permissions.php`、DB schema 文档。
2. **Filament 后台层**：`app/Filament/Platform`、`app/Filament/Merchant`、Vue3 嵌入面板。
3. **应用/领域层（Octane）**：`app/Domain`（模型+规则）、`app/Application`（用例）、`app/Http/Middleware`（生命周期）。
4. **Redis 分布式层**：`app/Infrastructure/Redis`（锁、限流、延迟/死信队列、计数）。
5. **MySQL 持久层**：`database/migrations`、`app/Models` + `TenantScope`。

## 依赖规则（MUST）

- **单向依赖**：上层可调用下层；下层 MUST NOT 感知上层（禁止 Domain 引用 Filament/Http）。
- **规范层无代码依赖**：Enum/DTO/OpenAPI 被各层引用，自身不依赖框架。
- **Filament MUST NOT 直连 Redis**：必须经 Application/Domain 服务层。
- **Domain MUST NOT 依赖 Filament/Http**：纯 PHP，可独立单元测试。
- **Infrastructure 可替换**：Redis 实现面向接口，Domain 不绑定具体客户端。

## 多租户隔离（MUST）

- 所有租户域模型 MUST 使用 `BelongsToTenant` trait（自动注册 `TenantScope` + 创建时填充 `tenant_id`）。
- `TenantScope` 在 `tenantId === null`（平台视图）时跳过过滤——平台可查全量，此为设计意图但属安全关键点。
- `SqlTenantGuard` 通过 `DB::listen` 检测 tenant 域表的 UPDATE/DELETE 缺 `tenant_id`（默认告警，严格模式抛异常）。
- `OctaneTenantCleanupMiddleware` MUST 在请求 `finally` 中 `forgetInstance(TenantContext::class)` 并重置为 null，防 Swoole 串号。

## 请求生命周期

开放 API：`ApiAuthMiddleware → ApiRateLimitMiddleware → SqlTenantGuard → Controller → Service → Model(TenantScope) → OctaneTenantCleanupMiddleware`。

Filament 后台：`Filament Auth(guard) → ResolveTenantContext → Resource/Model → OctaneTenantCleanupMiddleware`。
