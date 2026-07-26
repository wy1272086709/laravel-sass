<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

/**
 * Redis 日 API 调用计数（INCR + EXPIREAT）。
 *
 * 典型用途：开放 API 实时消耗计数，由 ApiUsageFlushJob（次日 00:05）落库 api_usage_daily。
 * Key 形如 saas:{tenantId}:api:daily:{YYYY-MM-DD}，保留 RETENTION_DAYS 天后过期，
 * 确保次日 flush job 仍能读到昨日计数（若当日终就过期，flush 会读到 0）。
 */
final class ApiDailyCounter
{
    /**
     * 计数 key 的保留天数。
     *
     * flush job 在次日 00:05 才落库昨日计数，若 key 在当日 23:59:59 过期，届时已被删除，
     * 落库的 request_count 会恒为 0，月结超额费因此算不出来。保留 N 天还能让 flush 因
     * worker 故障延迟数日时，仍能补跑历史计数。
     */
    private const RETENTION_DAYS = 7;

    /**
     * 自增当日计数并返回新值。
     */
    public function increment(int $tenantId, ?string $date = null): int
    {
        $date ??= Carbon::now()->toDateString();
        $key = KeyResolver::apiDailyCounter($tenantId, $date);

        $count = (int) Redis::incr($key);
        $this->ensureDailyExpiry($key, $date);

        return $count;
    }

    /**
     * 当日已用配额。
     */
    public function get(int $tenantId, ?string $date = null): int
    {
        $date ??= Carbon::now()->toDateString();
        $value = Redis::get(KeyResolver::apiDailyCounter($tenantId, $date));

        return $value === null ? 0 : (int) $value;
    }

    /**
     * 重置当日计数（测试 / 人工复位）。返回被删 key 数。
     */
    public function reset(int $tenantId, ?string $date = null): int
    {
        $date ??= Carbon::now()->toDateString();

        return (int) Redis::del(KeyResolver::apiDailyCounter($tenantId, $date));
    }

    /**
     * 仅当尚未设置过期时，设为「当日 + RETENTION_DAYS」（应用时区）结束过期。
     *
     * 不能设为当日终：flush job 次日 00:05 读取昨日 key 时它已过期被删，会读到 0，
     * 导致 api_usage_daily.request_count 恒为 0。
     */
    private function ensureDailyExpiry(string $key, string $date): void
    {
        if (Redis::ttl($key) >= 0) {
            return;
        }

        $expiry = Carbon::parse($date, config('app.timezone'))
            ->addDays(self::RETENTION_DAYS)
            ->endOfDay();

        Redis::expireAt($key, $expiry->getTimestamp());
    }
}
