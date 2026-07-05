<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

/**
 * Redis 死信队列（List，与 failed_jobs 联动）。
 *
 * 典型用途：队列任务重试 3 次仍失败，转入死信等待人工介入。
 * 每条记录带失败原因与时间，便于运维在「队列中心」面板查看。
 */
final class DeadLetterQueue
{
    /**
     * 推入死信。
     *
     * @param array<string, mixed> $payload
     * @return void
     */
    public function push(string $queue, array $payload, string $reason): void
    {
        $key = KeyResolver::deadLetterQueue($queue);
        $entry = json_encode([
            'payload' => $payload,
            'reason' => $reason,
            'failed_at' => Carbon::now()->toDateTimeString(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        Redis::rPush($key, $entry);
    }

    /**
     * 将一个失败任务转入死信（move 语义，等同于 push + 原因）。
     *
     * @param array<string, mixed> $jobPayload
     */
    public function move(string $queue, array $jobPayload, string $reason): void
    {
        $this->push($queue, $jobPayload, $reason);
    }

    /**
     * 读取死信列表（默认最近 100 条）。
     *
     * @return array<int, array{payload: mixed, reason: string, failed_at: string}>
     */
    public function list(string $queue, int $limit = 100): array
    {
        $key = KeyResolver::deadLetterQueue($queue);
        $rows = Redis::lRange($key, 0, max(0, $limit - 1));

        return array_map(
            static fn (string $row): array => json_decode($row, true, flags: JSON_THROW_ON_ERROR),
            $rows,
        );
    }

    /**
     * 死信条数（看板用）。
     */
    public function size(string $queue): int
    {
        return (int) Redis::lLen(KeyResolver::deadLetterQueue($queue));
    }
}
