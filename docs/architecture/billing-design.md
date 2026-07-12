# 阶段 5 账单闭环设计

## 目标

阶段 5 的账单模块负责把商户经营数据转成平台应收账单，并支持运营半自动对账。当前项目采用「商户独立收款，平台做账单状态流转」方案：不接支付渠道，只计算平台应收、记录商户实报、生成差异单。

## 现有基础

| 类型 | 文件/表 | 说明 |
|------|---------|------|
| 账单表 | `tenant_bills` | 月度账单，按 `tenant_id + billing_period` 唯一 |
| 差异表 | `reconciliation_discrepancies` | 实报与应收不一致时生成 |
| 订单表 | `orders` | 月结交易额来源，使用 `total_amount` |
| API 用量表 | `api_usage_daily` | API 日用量落库来源 |
| 套餐表 | `packages` | API 日配额与套餐价格来源 |
| 租户表 | `tenants` | `commission_rate`、`package_id` 来源 |
| Redis 计数 | `ApiDailyCounter` | 实时 API 调用计数，日终落库 |

## 核心职责

### `BillSettlementService`

放在 `app/Domain/Billing/BillSettlementService.php`，负责纯业务计算与差异生成。

职责：
- 计算账期：默认上月，格式 `YYYY-MM`。
- 计算交易总额：汇总账期内租户订单 `orders.total_amount`。
- 计算佣金：`commission_amount = transaction_total * tenant.commission_rate`。
- 计算 API 费用：读取账期内 `api_usage_daily`，按套餐日配额计算超额。
- 生成或更新 `tenant_bills`。
- 记录商户实报金额 `merchant_reported_amount`。
- 当 `total_receivable != merchant_reported_amount` 时生成差异单。

### `MonthlyBillingJob`

放在 `app/Jobs/MonthlyBillingJob.php`，按租户批量生成上月账单。

职责：
- 使用 `LuaDistributedLock` 获取月结互斥锁，锁 key 建议：`billing:monthly:{period}`。
- 遍历所有未删除租户。
- 调用 `BillSettlementService::generateMonthlyBill($tenant, $period)`。
- 为每个租户写入 `queue_job_logs`。
- 失败租户不影响其他租户，失败详情写入日志，后续 5-3 任务中心展示。

### `GenerateApiBillJob`

放在 `app/Jobs/GenerateApiBillJob.php`，作为月结子任务或手工重算入口。

职责：
- 汇总指定租户、指定账期的 API 用量。
- 计算 `api_usage_fee` 与 `api_overage_fee`。
- 更新对应 `tenant_bills`。

阶段 5-1 可以先由 `MonthlyBillingJob` 同步调用 `BillSettlementService` 完成 API 费用计算；`GenerateApiBillJob` 保留为单租户重算入口。

### `ApiUsageFlushJob`

放在 `app/Jobs/ApiUsageFlushJob.php`。

职责：
- 从 Redis `ApiDailyCounter` 读取指定日期每个租户的调用量。
- 写入或更新 `api_usage_daily`。
- 计算 `overage_count = max(0, request_count - package.api_quota_daily)`。

注意：当前 `ApiDailyCounter` 只有按租户读写的方法，没有全租户 key 扫描能力。因此 `ApiUsageFlushJob` 应遍历租户列表，再按 tenant id 读取 Redis 计数。

## 费用计算

### 交易与佣金

```text
transaction_total = sum(orders.total_amount where tenant_id = T and created_at in period)
commission_amount = round(transaction_total * tenants.commission_rate, 2)
```

`commission_rate` 使用数据库已有字段，精度以 `decimal(8,4)` 或当前迁移为准。

### API 费用

本项目已经把 API 配额按套餐存储在 `packages.api_quota_daily`。阶段 5-1 建议采用清晰、可解释的 MVP 计费规则：

```text
included_requests = api_quota_daily * days_in_period
request_count = sum(api_usage_daily.request_count in period)
overage_count = max(0, request_count - included_requests)
api_usage_fee = 0
api_overage_fee = round(overage_count * 0.001, 2)
```

说明：
- `api_usage_fee` 暂保留为 0，表示套餐内 API 不另收费。
- `api_overage_fee` 使用固定单价 0.001，后续如果套餐表增加 `api_overage_unit_price`，再切到配置化。
- 若你希望专业/企业版和基础版有不同单价，需要在阶段 5-1 实现前确认。

### 总应收

```text
total_receivable = commission_amount + api_usage_fee + api_overage_fee
```

## 对账流程

```text
MonthlyBillingJob
  -> generateMonthlyBill()
  -> tenant_bills.status = pending_settlement

运营在后台填 merchant_reported_amount
  -> BillSettlementService::recordMerchantReport()
  -> difference_amount = total_receivable - merchant_reported_amount
  -> difference_amount != 0 时生成 reconciliation_discrepancies
```

差异单规则：
- 同一账单允许保留最新一条未处理差异单。
- 重复提交相同实报金额时不重复创建差异单。
- 当差异变为 0，可将旧差异单标记为 `reconciled`，或保留历史。阶段 5-1 建议保留历史并更新账单 `difference_amount = 0`，差异单清理放到后续运营流程。

## 幂等与并发

| 场景 | 设计 |
|------|------|
| 月结任务重复触发 | `LuaDistributedLock` + `tenant_id,billing_period` 唯一键 |
| 单租户账单重复生成 | `updateOrCreate(['tenant_id', 'billing_period'])` |
| API 用量重复落库 | `updateOrCreate(['tenant_id', 'usage_date'])` |
| 对账重复提交 | 比较当前 `merchant_reported_amount` 和差异金额，避免重复差异 |
| 单租户生成失败 | 捕获异常，写 `queue_job_logs.failed`，继续下一个租户 |

## 状态流转

账单状态来自 `BillStatus`：

```text
pending_settlement -> settled
pending_settlement -> overdue
overdue -> settled
```

阶段 5-1 只负责生成 `pending_settlement` 和差异单。`settled`、`overdue` 可以由后台人工操作或后续任务处理。

## 日志

所有 Job 应写入 `queue_job_logs`，便于 5-3 队列中心读取。

建议字段：
- `job_uuid`: `Str::uuid()`
- `name`: Job 类名短名
- `queue`: `billing` / `default`
- `status`: `pending`、`processing`、`success`、`failed`
- `attempts`: 当前尝试次数
- `payload`: `tenant_id`、`period`、`date` 等
- `error`: 异常摘要
- `queued_at`、`started_at`、`finished_at`

## 测试清单

阶段 5-1 至少覆盖：
- `BillSettlementService` 根据订单与 API 用量生成月账单。
- `recordMerchantReport()` 在金额不一致时生成差异单。
- `recordMerchantReport()` 在金额一致时不生成差异单。
- `MonthlyBillingJob` 为多个租户生成账单，并保持幂等。
- `ApiUsageFlushJob` 从 Redis 写入 `api_usage_daily`。
- `CloseExpiredOrderJob` 关闭超时 `pending_payment` 订单。
- 轻量运营 Job 写入 `queue_job_logs.success`。

## 非目标

阶段 5-1 不做：
- 真实支付渠道对接。
- 发票、收款流水、退款流水。
- 复杂阶梯 API 计费。
- 对账差异的审批工作流。

这些可以作为阶段 7 或后续增强项。
