<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Api\ApiRequestLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 开放 API 请求日志异步落库（ApiRequestLogMiddleware 投递）。
 *
 * payload 仅含标量，避免队列序列化模型导致的陈旧数据；
 * tenant_id 可为 null（未过鉴权的 token 签发 / webhook / 拒绝请求）。
 */
class LogApiRequestJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $log
     */
    public function __construct(public readonly array $log) {}

    public function handle(): void
    {
        ApiRequestLog::query()->create($this->log);
    }
}
