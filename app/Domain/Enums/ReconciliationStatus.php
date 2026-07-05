<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 对账差异单状态（租户域表 reconciliation_discrepancies.status）。
 */
enum ReconciliationStatus: string
{
    case Unreconciled = 'unreconciled';
    case Reconciled = 'reconciled';

    public function label(): string
    {
        return match ($this) {
            self::Unreconciled => '未对账',
            self::Reconciled => '已对账',
        };
    }
}
