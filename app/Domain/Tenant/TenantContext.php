<?php

declare(strict_types=1);

namespace App\Domain\Tenant;

use App\Domain\Enums\PackageTier;

/**
 * 租户上下文（Octane 友好，禁止存放在静态变量中）
 */
readonly class TenantContext
{
    public function __construct(
        public ?int $tenantId,
        public ?int $impersonatorId,
        public PackageTier $tier,
    ) {
    }

    public function isPlatformView(): bool
    {
        return $this->tenantId === null;
    }

    public function isImpersonating(): bool
    {
        return $this->impersonatorId !== null;
    }
}

