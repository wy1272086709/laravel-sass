<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

/**
 * Redis 延迟队列（ZADD + 到期轮询消费）。
 *
 * 典型用途：订单超时关闭（下单后 30min 未支付）。payload 按到期时间戳为 score 入队，
 * 消费方轮询取出到期任务。
 *
 * 注意：本最小实现 poll 非原子（先查后删），适合单 worker 开发环境；
 * 高并发需用 Lua 原子取出（阶段 7 优化）。
 */
final class DelayQueue
{
    /**
     * 推入延迟任务。
     *
     * @param array<string, mixed> $payload
     */
    public function push(string $queue, array $payload, int $delaySeconds): void
    {
        $key = KeyResolver::delayQueue($queue);
        $executeAt = Carbon::now()->getTimestamp() + $delaySeconds;

        Redis::zAdd($key, $executeAt, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * 取出最早一个已到期任务（到期时间 <= now），并移除。
     *
     * @return array<string, mixed>|null
     */
    public function poll(string $queue, ?int $nowTs = null): ?array
    {
        $key = KeyResolver::delayQueue($queue);
        $nowTs ??= Carbon::now()->getTimestamp();

        $rows = Redis::zRangeByScore($key, '-inf', (string) $nowTs, ['limit' => ['offset' => 0, 'count' => 1]]);

        if (empty($rows)) {
            return null;
        }

        /** @var array<int,string> $rows */
        $raw = (string) reset($rows);
        Redis::zRem($key, $raw);

        return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * 队列中剩余任务数（调试/看板用）。
     */
    public function size(string $queue): int
    {
        return (int) Redis::zCard(KeyResolver::delayQueue($queue));
    }
}
