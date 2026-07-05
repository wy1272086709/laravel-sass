<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 风控告警处置状态（租户域表 risk_alerts.status）。
 */
enum RiskAlertStatus: string
{
    case Pending = 'pending';
    case Handled = 'handled';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待处理',
            self::Handled => '已处理',
            self::Ignored => '已忽略',
        };
    }
}
