<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 操作者值对象（Domain Service 跨层传递用）。
 *
 * 比 OperationLogContext 更小：Domain Service 不应感知完整审计 Context，
 * 但需要知道"谁发起的"。本 VO 只承载 actor 信息 + 可选 trace key。
 */
readonly class OperationActor
{
    public function __construct(
        public string $kind,           // platform_user/merchant_user/api_key/system
        public ?int $id,
        public string $label,
        public ?string $idempotencyKey = null,
    ) {}
}
