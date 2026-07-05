<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

/**
 * Redis 日 API 调用计数（INCR + 当日终 EXPIREAT）。
 *
 * 典型用途：开放 API 实时消耗计数，日终由 ApiUsageFlushJob 落库 api_usage_daily。
 * Key 形如 saas:{tenantId}:api:daily:{YYYY-MM-DD}，当日结束自动过期。
 */
final class ApiDailyCounter
{
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
     * 仅当尚未设置过期时，设为当日（应用时区）结束过期。
     */
    private function ensureDailyExpiry(string $key, string $date): void
    {
        if (Redis::ttl($key) >= 0) {
            return;
        }

        $endOfDay = Carbon::parse($date, config('app.timezone'))->endOfDay();
        Redis::expireAt($key, $endOfDay->getTimestamp());
    }
}
