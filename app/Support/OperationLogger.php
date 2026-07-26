<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\System\OperationLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 业务操作审计日志写入器。
 *
 * 通用入口：所有路径（HTTP / Job / Service）都通过本类落库。
 * 始终 withoutGlobalScopes + 显式 tenant_id（Job 路径无 TenantContext，规避 Octane 串号，对齐 QueueJobLogger）。
 * best-effort：内部吞异常转 Log::warning，不阻塞业务。
 */
final class OperationLogger
{
    public function log(OperationLogContext $ctx): ?OperationLog
    {
        try {
            return OperationLog::query()
                ->withoutGlobalScopes()
                ->create([
                    'tenant_id' => $ctx->tenantId,
                    'platform_user_id' => $ctx->actorKind === 'platform_user' ? $ctx->actorId : null,
                    'merchant_user_id' => $ctx->actorKind === 'merchant_user' ? $ctx->actorId : null,
                    'api_key_id' => $ctx->actorKind === 'api_key' ? $ctx->actorId : null,
                    'actor_kind' => $ctx->actorKind,
                    'actor_label' => $ctx->actorLabel,
                    'subject_type' => $ctx->subjectType,
                    'subject_id' => $ctx->subjectId,
                    'subject_label' => $ctx->subjectLabel,
                    'action' => $ctx->action,
                    'from_status' => $ctx->fromStatus,
                    'to_status' => $ctx->toStatus,
                    'payload' => $ctx->payload,
                    'idempotency_key' => $ctx->idempotencyKey,
                    'ip_address' => $ctx->ipAddress,
                    'user_agent' => $ctx->userAgent,
                ]);
        } catch (Throwable $e) {
            Log::warning('operation log failed', [
                'tenant_id' => $ctx->tenantId,
                'action' => $ctx->action->value,
                'subject_type' => $ctx->subjectType,
                'subject_id' => $ctx->subjectId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
