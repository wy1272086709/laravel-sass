<?php

declare(strict_types=1);

namespace App\Domain\Enums;

enum PackageTier: string
{
    case Basic = 'basic';
    case Professional = 'professional';
    case Enterprise = 'enterprise';

    public function hardBlockOnOverage(): bool
    {
        return $this === self::Basic;
    }

    public function allowsOverageBilling(): bool
    {
        return $this !== self::Basic;
    }

    public function label(): string
    {
        return match ($this) {
            self::Basic => '基础版',
            self::Professional => '专业版',
            self::Enterprise => '企业版',
        };
    }
}

