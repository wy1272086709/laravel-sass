<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 订单状态（租户域表 orders.status）。
 *
 * 合法迁移见 SDD §3.3：
 *   pending_payment ──► paid ──► shipped ──► completed
 *          │               │
 *          │               └──► refund_requested ──► completed / cancelled
 *          └──► cancelled（超时 Job / 手动）
 *
 * canTransitionTo() 由 OrderStateMachine 调用以拦截非法流转。
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case RefundRequested = 'refund_requested';
    case Cancelled = 'cancelled';

    /**
     * 当前状态是否可迁移到目标状态。
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, self::allowedTargets($this), true);
    }

    /**
     * 状态机迁移表：返回当前状态允许迁入的目标状态集合。
     *
     * @return array<int, self>
     */
    public static function allowedTargets(self $from): array
    {
        return match ($from) {
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Shipped, self::RefundRequested, self::Cancelled],
            self::Shipped => [self::Completed],
            self::RefundRequested => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => '待付款',
            self::Paid => '已付款',
            self::Shipped => '已发货',
            self::Completed => '已完成',
            self::RefundRequested => '退款申请',
            self::Cancelled => '已取消',
        };
    }
}
