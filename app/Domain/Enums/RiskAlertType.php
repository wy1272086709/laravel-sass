<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 风控告警类型（租户域表 risk_alerts.type）。对应内置 5 条风控规则。
 */
enum RiskAlertType: string
{
    case BrushOrder = 'brush_order';
    case DuplicatePayment = 'duplicate_payment';
    case AbnormalLogin = 'abnormal_login';
    case HighRefundRate = 'high_refund_rate';

    public function label(): string
    {
        return match ($this) {
            self::BrushOrder => '刷单',
            self::DuplicatePayment => '重复支付',
            self::AbnormalLogin => '异地登录',
            self::HighRefundRate => '高退款率',
        };
    }
}
