<?php

declare(strict_types=1);

namespace App\Application\Api;

use App\Domain\Enums\PackageTier;

/**
 * 开放 API 配额分级策略（SDD §3.5）。
 *
 * - 基础版：用量 >= 配额 → 硬阻断（429）。
 * - 专业版/企业版：[配额, 1.5×配额) 软告警 + 超额计费，不阻断；
 *   达到 1.5×配额 → 全局硬阻断（429）。
 */
final class QuotaPolicyService
{
    /** 全局硬阻断阈值倍数。 */
    public const GLOBAL_HARD_BLOCK_FACTOR = 1.5;

    /**
     * 本次请求是否应被阻断。
     */
    public function shouldBlock(PackageTier $tier, int $used, int $quota): bool
    {
        if ($tier->hardBlockOnOverage() && $used >= $quota) {
            return true;
        }

        return $used >= $this->globalHardBlockThreshold($quota);
    }

    /**
     * 是否处于软告警区间（仅专业/企业版、超额但未达硬阻断）。
     */
    public function isSoftWarning(PackageTier $tier, int $used, int $quota): bool
    {
        if ($this->shouldBlock($tier, $used, $quota)) {
            return false;
        }

        return $tier->allowsOverageBilling() && $used >= $quota;
    }

    /**
     * 全局硬阻断阈值（向上取整）。
     */
    public function globalHardBlockThreshold(int $quota): int
    {
        return (int) ceil($quota * self::GLOBAL_HARD_BLOCK_FACTOR);
    }
}
