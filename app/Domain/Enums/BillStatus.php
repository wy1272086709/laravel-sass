<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 月度账单状态（租户域表 tenant_bills.status）。
 *
 * 本期账单仅状态流转（A 方案）：pending_settlement → settled；
 * payment_channel 等字段为 C 预留，不对接支付渠道。
 */
enum BillStatus: string
{
    case PendingSettlement = 'pending_settlement';
    case Settled = 'settled';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::PendingSettlement => '待结算',
            self::Settled => '已结算',
            self::Overdue => '逾期',
        };
    }
}
