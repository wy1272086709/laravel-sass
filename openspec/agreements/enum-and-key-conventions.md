# Agreement: 枚举与 Redis Key 约定

## PHP Enum（MUST）

- 所有状态/类型字段 MUST 用 **PHP 8.3 backed string enum**，命名空间 `App\Domain\Enums\*`。
- Enum 值与 SDD §3.1 / 数据库文档严格一致，MUST NOT 擅自改名（影响 DB 数据与 OpenAPI 契约）。
- 每个 Enum SHOULD 提供 `label(): string`（中文，供 Filament 展示）。
- 状态机类 Enum（如 `OrderStatus`）以 `canTransitionTo(self): bool` 暴露合法迁移，由 Domain 层 `*StateMachine` 调用。

16 个枚举清单：`PackageTier`、`TenantStatus`、`ProductStatus`、`OrderStatus`、`CouponType`、`CouponStatus`、`ApiKeyStatus`、`ApiPermission`、`BillStatus`、`RiskLevel`、`RiskAlertType`、`RiskAlertStatus`、`ReconciliationStatus`、`PackageChangeType`、`QueueJobStatus`、`LoginResult`。

> 已在阶段 1 落地于 [app/Domain/Enums/](../../app/Domain/Enums/)。

## Redis Key 命名（MUST）

统一前缀规范 `saas:{tenant_id?}:{module}:{identifier}`：

| 用途       | 模式                                        | 是否租户级 |
|------------|---------------------------------------------|-----------|
| API 日配额 | `saas:{tenantId}:api:daily:{YYYY-MM-DD}`   | 是        |
| 滑动窗口   | `saas:{tenantId}:ratelimit:{module}:{id}`  | 是        |
| 分布式锁   | `saas:lock:{module}:{identifier}`          | 否（全局）|
| 延迟队列   | `saas:delay:{queue}`                        | 否（全局）|
| 死信队列   | `saas:deadletter:{queue}`                   | 否（全局）|

- 所有 key MUST 经 [app/Infrastructure/Redis/KeyResolver.php](../../app/Infrastructure/Redis/KeyResolver.php) 构造，禁止散落拼接。
- 客户端为 **predis**（本机无 phpredis 扩展）；Lua 脚本 MUST 经 `Redis::eval` 调用以保证客户端无关与原子性。

## 数据库精度（MUST 保留）

- `tenants.commission_rate` = `decimal(5,4)`（默认 0.0200 = 2%）。
- `orders.total_amount` = `decimal(12,2)`；`tenant_bills.transaction_total` = `decimal(14,2)`。
- `tenant_bills.billing_period` = `string(7)`（`YYYY-MM`）。
