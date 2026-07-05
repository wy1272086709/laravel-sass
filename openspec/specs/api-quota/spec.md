# 开放 API 配额 (api-quota)

## Purpose

对 `/api/v1` 开放网关的调用按商户套餐进行分级配额管控，防止滥用并对超额计费。

## Requirements

### Requirement: 分级阻断策略
系统 SHALL 按 `PackageTier` 应用配额策略（见 `App\Application\Api\QuotaPolicyService`）：

- **基础版**：`used >= quota` → 硬阻断（429）。
- **专业版 / 企业版**：`quota <= used < quota*1.5` → 软告警 + 超额计费（不阻断）；`used >= ceil(quota*1.5)` → 全局硬阻断（429）。

#### Scenario: 基础版达配额即阻断
- WHEN basic 套餐商户当日 `used` 从 9999 增至 10000
- THEN 下一次请求返回 HTTP 429，业务码 42901。

#### Scenario: 专业版超额未达 150% 放行
- WHEN professional 套餐 `used = 120000`、`quota = 100000`
- THEN 请求放行（200），仅记录软告警。

#### Scenario: 专业版达 150% 全局阻断
- WHEN professional 套餐 `used = 150000`、`quota = 100000`
- THEN 返回 HTTP 429，业务码 42901。

### Requirement: 日计数实现
系统 SHALL 使用 `App\Infrastructure\Redis\ApiDailyCounter`（`INCR` + 当日终 `EXPIREAT`）实时计数，key 形如 `saas:{tenantId}:api:daily:{YYYY-MM-DD}`，日终由 `ApiUsageFlushJob` 落库 `api_usage_daily`。

### Requirement: 配额阻断响应体
429 响应 SHALL 返回 `{ code:42901, message, data:{ tier, quota, used } }`（见 [agreements/api-error-format.md](../../agreements/api-error-format.md)）。

### Requirement: 平台视图不受配额
- WHEN `TenantContext.tenantId` 为 `null`（平台内部调用）
- THEN `ApiRateLimitMiddleware` 不按配额阻断。

## Status

- ✅ QuotaPolicyService、ApiDailyCounter、ApiRateLimitMiddleware（阶段 1）
- ⏳ 真实配额从 `packages.api_quota_daily` 读取、AccessToken 鉴权链路、ApiUsageFlushJob（阶段 4-5）
