<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 风险等级。用于 risk_rules（平台域）与 risk_alerts（租户域）。
 */
enum RiskLevel: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High => '高',
            self::Medium => '中',
            self::Low => '低',
        };
    }
}
