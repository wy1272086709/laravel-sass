<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * API 密钥状态（租户域表 api_keys.status）。
 */
enum ApiKeyStatus: string
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
