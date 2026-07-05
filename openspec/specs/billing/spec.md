# 月结账单与对账 (billing)

## Purpose

每月自动生成商户账单（平台应收），运营半自动核对商户实报金额，差异自动生成对账单。

## Requirements

### Requirement: 账单仅状态流转（A 方案）
本期账单 SHALL 仅做状态流转，不对接支付渠道。状态取自 `App\Domain\Enums\BillStatus`：`pending_settlement → settled`（`overdue` 为逾期态）。

### Requirement: 月结自动生成
`MonthlyBillingJob`（每月 1 日 02:00）SHALL 为每个租户汇总上月交易总额，计算 `commission = transaction_total × commission_rate`，汇总 API 费，生成 `tenant_bills(status = pending_settlement)`。月结任务 SHALL 经 `LuaDistributedLock` 互斥，防重复结算。

### Requirement: 支付字段预留（C）
`tenant_bills` SHALL 预留但本期不使用：`payment_channel`、`external_transaction_no`、`paid_at`、`payment_meta`。

### Requirement: 半自动对账差异单
当运营填入 `merchant_reported_amount` 且与 `total_receivable` 不等时，系统 SHALL 自动生成 `reconciliation_discrepancies` 记录（`difference_amount = total_receivable − merchant_reported_amount`）。

#### Scenario: 差异生成
- GIVEN 账单 `total_receivable = 8420.00`
- WHEN 运营填 `merchant_reported_amount = 8400.00`
- THEN 生成一条差异单，`difference_amount = 20.00`。

### Requirement: 账单精度
`tenant_bills.transaction_total` = `decimal(14,2)`；`commission_amount`/`total_receivable`/`merchant_reported_amount`/`difference_amount` = `decimal(12,2)`；`billing_period` = `string(7)`（`YYYY-MM`）。

## Status

- ✅ `BillStatus` 枚举、`BillSettlementService` 接口约定（阶段 1）
- ⏳ `tenant_bills`/`reconciliation_discrepancies` 迁移与模型、`MonthlyBillingJob`、`BillSettlementService` 实现（阶段 5）
