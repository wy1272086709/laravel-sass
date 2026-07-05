# 订单状态机 (order-workflow)

## Purpose

约束订单生命周期状态流转，拦截非法迁移，并驱动超时关闭、发货、退款等流程。

## Requirements

### Requirement: 订单状态枚举
订单状态 SHALL 取自 `App\Domain\Enums\OrderStatus`：`pending_payment`、`paid`、`shipped`、`completed`、`refund_requested`、`cancelled`。

### Requirement: 合法迁移表
`OrderStatus::canTransitionTo()` SHALL 仅允许以下迁移（其余返回 `false`）：

- `pending_payment → paid | cancelled`
- `paid → shipped | refund_requested | cancelled`
- `shipped → completed`
- `refund_requested → completed | cancelled`

#### Scenario: 非法迁移被拒
- WHEN 订单为 `pending_payment` 并试图迁到 `shipped`
- THEN `canTransitionTo(Shipped)` 返回 `false`，`OrderStateMachine` 抛 `DomainException`。

### Requirement: 迁移副作用
`OrderStateMachine::transition()` SHALL 在迁移时设置对应时间戳：`paid → paid_at`、`shipped → shipped_at`、`cancelled → cancelled_at`；`completed`/`refund_requested` 不设时间戳。

### Requirement: 超时关闭
下单后 30 分钟未支付，`CloseExpiredOrderJob`（经 `DelayQueue` 延迟触发）SHALL 将订单从 `pending_payment` 迁至 `cancelled`。

## Status

- ✅ `OrderStatus` 枚举 + `canTransitionTo`（阶段 1）
- ⏳ `OrderStateMachine` 实现、`CloseExpiredOrderJob`、订单迁移 API（阶段 4-5）
