# Redis 分布式基础设施 (redis-infrastructure)

## Purpose

提供高性能、原子的分布式原语：互斥锁、滑动窗口限流、延迟队列、死信队列、日计数，支撑月结互斥、API 配额、订单超时关闭等场景。

## Requirements

### Requirement: Lua 原子分布式锁
`App\Infrastructure\Redis\LuaDistributedLock` SHALL 以 `SET NX PX` + token 获取锁、Lua 比对 token 释放。

#### Scenario: 互斥
- WHEN 同一 (module, identifier) 已被持有时再次 acquire
- THEN 返回 `null`。

#### Scenario: 仅持有人释放
- WHEN 以错误 token 调用 release
- THEN 返回 `false`，锁不被释放。

### Requirement: 滑动窗口限流
`SlidingWindowRateLimiter` SHALL 以 Sorted Set + Lua 原子地"清窗口外成员 → 计数 → 未满则记入"。规范方法 `tooManyAttempts(key, limit, windowSeconds): bool`（true=超限）。

#### Scenario: 达到上限拦截
- WHEN `limit=3` 且窗口内已记 3 次
- THEN 第 4 次 `tooManyAttempts` 返回 `true`。

### Requirement: 延迟队列
`DelayQueue` SHALL 以 `ZADD`（score=到期时间戳）入队，`poll` 取出到期任务并移除。

#### Scenario: 仅取到期任务
- WHEN 队列含立即到期与 1h 后到期各一条
- THEN `poll` 返回立即到期者，且队列仍保留未到期者。

### Requirement: 死信队列
`DeadLetterQueue` SHALL 以 List 存储失败任务，每条含 `payload`/`reason`/`failed_at`，队列任务重试 3 次失败后转入。

### Requirement: 日 API 计数
`ApiDailyCounter` SHALL `INCR` 当日计数并在当日终过期（见 [api-quota](../api-quota/spec.md)）。

### Requirement: 统一 Key 命名
所有 key SHALL 经 `KeyResolver` 以 `saas:{tenant_id?}:{module}:{identifier}` 构造（见 [agreements/enum-and-key-conventions.md](../../agreements/enum-and-key-conventions.md)）。

## Status

- ✅ 全部 5 工具 + KeyResolver，已连真实 Redis 单测验证（阶段 1）
