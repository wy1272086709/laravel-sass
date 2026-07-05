<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

/**
 * Redis Key 统一命名规范：saas:{tenant_id?}:{module}:{identifier}
 *
 * - 租户级 key 带 tenant_id（如 API 日配额）。
 * - 平台全局 key 省略 tenant_id（如月结互斥锁、延迟/死信队列）。
 *
 * 所有方法为纯字符串拼接、无副作用，便于在测试中构造期望 key。
 */
final class KeyResolver
{
    public const PREFIX = 'saas';

    /** 租户 API 日配额计数：saas:{tenantId}:api:daily:{YYYY-MM-DD} */
    public static function apiDailyCounter(int $tenantId, string $date): string
    {
        return self::build($tenantId, ['api', 'daily', $date]);
    }

    /** 分布式锁：saas:lock:{module}:{identifier}（平台全局） */
    public static function lock(string $module, string $identifier): string
    {
        return self::build(null, ['lock', $module, $identifier]);
    }

    /** 滑动窗口限流：saas:{tenantId}:ratelimit:{module}:{identifier} */
    public static function slidingWindow(int $tenantId, string $module, string $identifier): string
    {
        return self::build($tenantId, ['ratelimit', $module, $identifier]);
    }

    /** 延迟队列：saas:delay:{queue}（平台全局） */
    public static function delayQueue(string $queue): string
    {
        return self::build(null, ['delay', $queue]);
    }

    /** 死信队列：saas:deadletter:{queue}（平台全局） */
    public static function deadLetterQueue(string $queue): string
    {
        return self::build(null, ['deadletter', $queue]);
    }

    /** @param array<int,string> $parts */
    private static function build(?int $tenantId, array $parts): string
    {
        $segments = [self::PREFIX];
        if ($tenantId !== null) {
            $segments[] = (string) $tenantId;
        }

        return implode(':', array_merge($segments, $parts));
    }
}
