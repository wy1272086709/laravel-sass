<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 套餐变更类型（平台域表 package_change_logs.change_type，审计日志不随租户级联删除）。
 */
enum PackageChangeType: string
{
    case NewPurchase = 'new_purchase';
    case Upgrade = 'upgrade';
    case Downgrade = 'downgrade';

    public function label(): string
    {
        return match ($this) {
            self::NewPurchase => '新购',
            self::Upgrade => '升级',
            self::Downgrade => '降级',
        };
    }
}
