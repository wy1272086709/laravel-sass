<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 租户状态。平台域表 tenants.status，无 TenantScope（平台可查全量）。
 */
enum TenantStatus: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Enabled => '启用',
            self::Disabled => '停用',
        };
    }
}
