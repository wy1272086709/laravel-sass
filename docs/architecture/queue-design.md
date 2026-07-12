# 阶段 5 队列与调度设计

## 目标

阶段 5 的队列模块负责把平台从同步 CRUD 推进到可运行的业务系统：订单超时关闭、月结、API 用量落库、风控扫描、库存预警、物流同步和任务中心观测。

当前项目已经有 Redis 基础设施：
- `LuaDistributedLock`
- `DelayQueue`
- `DeadLetterQueue`
- `ApiDailyCounter`
- `KeyResolver`

阶段 5 在此基础上实现 Job、任务日志、调度注册和内部运维接口。

## Job 清单

| Job | 队列 | 触发 | 阶段 | 说明 |
|-----|------|------|------|------|
| `CloseExpiredOrderJob` | `default` | 下单后 30 分钟 / 调度补偿 | 5-1 | 关闭超时未支付订单 |
| `MonthlyBillingJob` | `billing` | 每月 1 日 02:00 | 5-1 | 生成上月租户账单 |
| `GenerateApiBillJob` | `billing` | 月结子任务 / 手动重算 | 5-1 | 计算 API 用量与超额费 |
| `ApiUsageFlushJob` | `default` | 每日 00:05 | 5-1 | Redis API 计数落库 |
| `MerchantWelcomeEmailJob` | `notification` | 租户创建后 | 5-1 | Mock 欢迎通知并记录任务日志 |
| `InventoryAlertJob` | `default` | 定时 / 库存变更后 | 5-1 | 低库存商品预警，阶段 5-1 先写日志 |
| `SyncLogisticsJob` | `sync_data` | 发货后 | 5-1 | Mock 物流同步并记录任务日志 |
| `RiskRuleScanJob` | `default` | 每小时 | 5-2 | 扫描风控规则并生成风险告警 |

## 执行原则

### 1. Job 必须幂等

每个 Job 都需要能安全重跑。

| Job | 幂等键 |
|-----|--------|
| `CloseExpiredOrderJob` | `order_id + expected status` |
| `MonthlyBillingJob` | `billing_period` + `tenant_bills(tenant_id,billing_period)` 唯一键 |
| `GenerateApiBillJob` | `tenant_id + billing_period` |
| `ApiUsageFlushJob` | `api_usage_daily(tenant_id,usage_date)` 唯一键 |
| `RiskRuleScanJob` | `tenant_id + rule + period/window` |

### 2. Job 必须写 `queue_job_logs`

`queue_job_logs` 是阶段 5-3 任务中心的数据源，也是开发期排错依据。

建议封装一个轻量 helper 或 trait，例如：

```text
recordJobStart(name, queue, tenant_id, payload)
markJobSuccess(log, payload)
markJobFailed(log, throwable)
```

不强制抽象过早，但所有 Job 的日志字段必须一致。

### 3. 失败进入可观测状态

阶段 5-1 不强制接 Laravel 队列失败事件，但 Job 自己应捕获业务异常并写 `queue_job_logs.failed`。

阶段 5-3 再把连续失败或三次失败的任务推入 `DeadLetterQueue`，用于队列中心展示。

## Redis 组件用法

### `DelayQueue`

用途：
- 订单创建后写入延迟队列：`queue = close_expired_orders`。
- payload 建议：`order_id`、`tenant_id`、`expires_at`。
- 到期后由调度命令轮询，派发 `CloseExpiredOrderJob`。

阶段 5-1 可先直接测试 `CloseExpiredOrderJob`，阶段 5-3 再补调度轮询入口。

### `DeadLetterQueue`

用途：
- Job 超过重试上限仍失败时，把 payload + reason 推入死信队列。
- 队列中心读取 `DeadLetterQueue::list()` 展示。

阶段 5-1 只要求 Job 日志可观测；死信联动放到 5-3。

### `LuaDistributedLock`

用途：
- `MonthlyBillingJob` 防重复执行。
- 锁 key 建议：`billing:monthly:{period}`。
- TTL 建议：10 分钟。任务完成后释放锁。

### `ApiDailyCounter`

用途：
- API 请求中实时 `INCR`。
- `ApiUsageFlushJob` 每日把 Redis 计数落库到 `api_usage_daily`。

## 调度设计

`routes/console.php` 负责注册 Laravel Scheduler。

建议调度：

```php
Schedule::job(new MonthlyBillingJob())->monthlyOn(1, '02:00')->onQueue('billing');
Schedule::job(new ApiUsageFlushJob(now()->subDay()->toDateString()))->dailyAt('00:05');
Schedule::job(new RiskRuleScanJob())->hourly();
Schedule::command('queue:poll-expired-orders')->everyMinute();
```

说明：
- `CloseExpiredOrderJob` 的精确 30 分钟延迟由 `DelayQueue` 承担。
- 每分钟调度只负责 poll 到期 payload 并派发 Job。
- 月结必须加分布式锁，避免多 worker 或重复调度生成重复账单。

## Job 设计细节

### `CloseExpiredOrderJob`

输入：
- `order_id`

逻辑：
- 读取订单。
- 如果状态不是 `pending_payment`，直接记 success，说明无需处理。
- 如果 `created_at > now() - 30min`，不关闭。
- 否则更新为 `cancelled`，写 `cancel_reason = timeout_unpaid`、`cancelled_at = now()`。

### `MonthlyBillingJob`

输入：
- 可选 `period`，默认上月。

逻辑：
- 获取月结锁。
- 遍历租户。
- 调用 `BillSettlementService` 生成账单。
- 写每个租户的任务日志。
- 释放锁。

### `ApiUsageFlushJob`

输入：
- `usage_date`，默认昨天。

逻辑：
- 遍历租户。
- 从 `ApiDailyCounter::get($tenantId, $date)` 读取。
- 读取套餐配额。
- `overage_count = max(0, count - quota)`。
- `updateOrCreate` 到 `api_usage_daily`。

### 轻量运营 Job

`MerchantWelcomeEmailJob`、`InventoryAlertJob`、`SyncLogisticsJob` 阶段 5-1 都采用 mock 外部动作：
- 不调用真实邮件、库存系统、物流系统。
- 只把动作结果写进 `queue_job_logs.payload`。
- 真实外部集成放后续阶段。

## 内部任务中心接口

阶段 5-3 实现 `QueueOpsController`。

建议接口：
- `GET /internal/queue-ops/summary`
- `GET /internal/queue-ops/jobs`
- `GET /internal/queue-ops/dead-letters`
- `GET /internal/queue-ops/delayed`

数据源：
- `queue_job_logs`
- `DeadLetterQueue`
- `DelayQueue`

安全：
- 仅平台后台 guard 可访问。
- 不暴露给 `/api/v1` 开放 API。

## 测试清单

阶段 5-1：
- `CloseExpiredOrderJob` 关闭超时待支付订单。
- `CloseExpiredOrderJob` 不关闭已支付订单。
- `ApiUsageFlushJob` 落库 Redis 计数。
- `MonthlyBillingJob` 生成账单并幂等。
- 轻量运营 Job 写入 success 日志。

阶段 5-2：
- 规则引擎命中 5 条内置规则。
- `RiskRuleScanJob` 生成告警且不重复。

阶段 5-3：
- `schedule:list` 包含月结、风控扫描、API 落库。
- 队列中心接口返回日志、延迟队列、死信队列。

## 非目标

阶段 5 不做：
- 真实邮件发送。
- 真实物流 API。
- Laravel Horizon 接入。
- 复杂失败重试策略 UI。

这些可以等第 6 阶段可视化面板稳定后再增强。
