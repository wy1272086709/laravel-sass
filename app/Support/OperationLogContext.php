<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Enums\OperationAction;

/**
 * 操作日志完整上下文（Controller / Filament 调用点构造，传给 OperationLogger）。
 */
readonly class OperationLogContext
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        public int $tenantId,
        public string $actorKind,
        public ?int $actorId,
        public string $actorLabel,
        public string $subjectType,
        public ?int $subjectId,
        public ?string $subjectLabel,
        public OperationAction $action,
        public ?string $fromStatus = null,
        public ?string $toStatus = null,
        public ?array $payload = null,
        public ?string $idempotencyKey = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
    ) {}
}
