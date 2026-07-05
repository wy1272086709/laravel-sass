<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Illuminate\Support\Facades\Redis;

/**
 * 滑动窗口限流（Sorted Set + Lua 原子脚本）。
 *
 * 典型用途：开放 API 日配额 / QPS 限流。窗口内每个请求记为一个 ZSET 成员，
 * 请求时先清除过期成员再计数，未达上限则写入。
 *
 * 注：API 日配额的实际"是否阻断"判定在 QuotaPolicyService（分级策略），
 * 本工具只负责窗口计数。
 */
final class SlidingWindowRateLimiter
{
    /**
     * Lua：清除窗口外成员 → 计数 → 未满则记一次返回 0，已满返回 1。
     *
     * ARGV[1]=now（秒，float）ARGV[2]=window 秒 ARGV[3]=limit ARGV[4]=唯一后缀
     *
     * @var string
     */
    private const LUA = <<<'LUA'
        local key = KEYS[1]
        local now = tonumber(ARGV[1])
        local window = tonumber(ARGV[2])
        local limit = tonumber(ARGV[3])
        redis.call('ZREMRANGEBYSCORE', key, 0, now - window)
        local count = redis.call('ZCARD', key)
        if count < limit then
            redis.call('ZADD', key, now, now .. ':' .. ARGV[4])
            redis.call('EXPIRE', key, window)
            return 0
        end
        return 1
    LUA;

    /**
     * 是否超限。
     *
     * @return bool true=已超限（应拦截，本次未记入），false=放行（已记一次）。
     */
    public function tooManyAttempts(string $key, int $limit, int $windowSeconds): bool
    {
        $now = (string) microtime(true);
        $unique = bin2hex(random_bytes(8));

        return (bool) Redis::eval(self::LUA, 1, $key, $now, (string) $windowSeconds, (string) $limit, $unique);
    }

    /**
     * 别名：返回 true=允许（已记一次），false=超限。语义对齐 Laravel RateLimiter::attempt。
     */
    public function attempt(string $key, int $limit, int $windowSeconds): bool
    {
        return ! $this->tooManyAttempts($key, $limit, $windowSeconds);
    }
}
