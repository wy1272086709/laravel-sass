<?php

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * 队列任务日志状态（租户域表 queue_job_logs.status）。
 *
 * failed 任务重试 3 次后进入 DeadLetterQueue，状态置为 dead。
 */
enum QueueJobStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Dead = 'dead';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待执行',
            self::Processing => '执行中',
            self::Success => '成功',
            self::Failed => '失败',
            self::Dead => '死信',
        };
    }
}
