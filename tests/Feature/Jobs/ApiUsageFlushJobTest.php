<?php

use App\Infrastructure\Redis\ApiDailyCounter;
use App\Jobs\ApiUsageFlushJob;
use App\Models\Api\ApiUsageDaily;
use App\Models\Tenant\Tenant;
use App\Support\QueueJobLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 端到端：验证 flush job 把前一日 Redis 计数正确落库到 api_usage_daily。
// 用固定未来日期，保证 Redis key 处于「尚未过期」的常态，聚焦落库主路径。
it('flushes the prior-day count into api_usage_daily', function () {
    $tenant = Tenant::factory()->create();
    $date = '2099-12-30';
    $counter = app(ApiDailyCounter::class);

    $counter->reset($tenant->id, $date);
    // 模拟当日 3 次 API 调用
    $counter->increment($tenant->id, $date);
    $counter->increment($tenant->id, $date);
    $counter->increment($tenant->id, $date);

    app(ApiUsageFlushJob::class, ['usageDate' => $date])->handle(
        app(ApiDailyCounter::class),
        app(QueueJobLogger::class),
    );

    $row = ApiUsageDaily::query()->withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->where('usage_date', $date)
        ->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->request_count)->toBe(3);

    $counter->reset($tenant->id, $date);
});

// flush 落库时按 (tenant_id, usage_date) upsert，重跑幂等且不重复建行。
it('upserts by tenant and date idempotently', function () {
    $tenant = Tenant::factory()->create();
    $date = '2099-12-29';
    $counter = app(ApiDailyCounter::class);

    $counter->reset($tenant->id, $date);
    $counter->increment($tenant->id, $date);

    $run = fn () => app(ApiUsageFlushJob::class, ['usageDate' => $date])->handle(
        app(ApiDailyCounter::class),
        app(QueueJobLogger::class),
    );

    $run();
    $counter->increment($tenant->id, $date); // 计数增长到 2
    $run(); // 重跑应覆盖而非新增行

    expect(ApiUsageDaily::query()->withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)->where('usage_date', $date)->count())->toBe(1)
        ->and((int) ApiUsageDaily::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('usage_date', $date)->first()->request_count)->toBe(2);

    $counter->reset($tenant->id, $date);
});
