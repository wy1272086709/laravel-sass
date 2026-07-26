<?php

use App\Infrastructure\Redis\ApiDailyCounter;
use App\Infrastructure\Redis\KeyResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->counter = app(ApiDailyCounter::class);
    $this->date = '2099-12-31'; // 固定未来日期，避免与真实数据冲突
    $this->counter->reset(888002, $this->date);
});

afterEach(function () {
    $this->counter->reset(888002, $this->date);
});

it('increments and reads the daily count', function () {
    expect($this->counter->increment(888002, $this->date))->toBe(1)
        ->and($this->counter->increment(888002, $this->date))->toBe(2)
        ->and($this->counter->increment(888002, $this->date))->toBe(3)
        ->and($this->counter->get(888002, $this->date))->toBe(3);
});

it('returns zero for an untouched tenant date', function () {
    expect($this->counter->get(888099, $this->date))->toBe(0);
});

it('can be reset', function () {
    $this->counter->increment(888002, $this->date);
    $this->counter->increment(888002, $this->date);

    expect($this->counter->reset(888002, $this->date))->toBe(1)
        ->and($this->counter->get(888002, $this->date))->toBe(0);
});

// 回归：counter 的 TTL 必须留到「次日 flush 之后」，否则 ApiUsageFlushJob 在
// 次日 00:05 读昨日计数时 key 已被 Redis 删除，落库 request_count 会恒为 0，
// 月结超额费因此算不出来。Redis server 用自身时钟判定过期，不认 PHP 的 time travel，
// 故这里直接断言「过期时刻晚于次日 flush 时刻」这一根因。
it('stays alive past the next-day flush window', function () {
    $this->counter->increment(888002, $this->date);

    $ttl = Redis::ttl(KeyResolver::apiDailyCounter(888002, $this->date));
    $expiryTs = Carbon::now()->getTimestamp() + $ttl;

    // flush job 在次日 00:05 执行，key 必须活过该时刻
    $flushTs = Carbon::parse($this->date, config('app.timezone'))
        ->addDay()
        ->setTime(0, 5)
        ->getTimestamp();

    expect($expiryTs)->toBeGreaterThan($flushTs);
});
