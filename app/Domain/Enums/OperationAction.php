<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 业务操作动作（通用审计动作枚举）。
 *
 * 与 subject_type 组合表达具体含义，如 (subject_type='order', action='shipped')。
 * 系统触发与人工触发用 actor_kind 区分，不在 action 上拆分，避免 case 随业务膨胀。
 * 对应表 operation_logs.action。
 */
enum OperationAction: string
{
    case Created = 'created';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
    case RefundRequested = 'refund_requested';
    case Updated = 'updated';

    public function label(): string
    {
        return match ($this) {
            self::Created => '创建',
            self::Shipped => '发货',
            self::Cancelled => '取消',
            self::RefundRequested => '退款申请',
            self::Updated => '更新',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $action): array => [$action->value => $action->label()])
            ->all();
    }
}
