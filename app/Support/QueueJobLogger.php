<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Enums\QueueJobStatus;
use App\Models\System\QueueJobLog;
use Illuminate\Support\Str;
use Throwable;

final class QueueJobLogger
{
    /** @param array<string, mixed> $payload */
    public function start(string $name, ?int $tenantId, array $payload = [], string $queue = 'default'): QueueJobLog
    {
        return QueueJobLog::query()
            ->withoutGlobalScopes()
            ->create([
                'tenant_id' => $tenantId,
                'job_uuid' => (string) Str::uuid(),
                'name' => $name,
                'queue' => $queue,
                'status' => QueueJobStatus::Processing,
                'attempts' => 1,
                'payload' => $payload,
                'queued_at' => now(),
                'started_at' => now(),
            ]);
    }

    /** @param array<string, mixed>|null $payload */
    public function success(QueueJobLog $log, ?array $payload = null): QueueJobLog
    {
        $log->forceFill([
            'status' => QueueJobStatus::Success,
            'payload' => $payload ?? $log->payload,
            'finished_at' => now(),
        ])->save();

        return $log->refresh();
    }

    public function failed(QueueJobLog $log, Throwable $throwable): QueueJobLog
    {
        $log->forceFill([
            'status' => QueueJobStatus::Failed,
            'error' => $throwable->getMessage(),
            'finished_at' => now(),
        ])->save();

        return $log->refresh();
    }
}
