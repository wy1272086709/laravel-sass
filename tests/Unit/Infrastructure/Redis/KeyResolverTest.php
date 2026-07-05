<?php

use App\Infrastructure\Redis\KeyResolver;

it('builds tenant-scoped keys with tenant_id segment', function () {
    expect(KeyResolver::apiDailyCounter(7, '2026-07-02'))->toBe('saas:7:api:daily:2026-07-02')
        ->and(KeyResolver::slidingWindow(7, 'qps', '127.0.0.1'))->toBe('saas:7:ratelimit:qps:127.0.0.1');
});

it('builds platform-global keys without tenant_id segment', function () {
    expect(KeyResolver::lock('billing', '2026-06'))->toBe('saas:lock:billing:2026-06')
        ->and(KeyResolver::delayQueue('default'))->toBe('saas:delay:default')
        ->and(KeyResolver::deadLetterQueue('default'))->toBe('saas:deadletter:default');
});

it('always prefixes with the saas namespace', function () {
    expect(KeyResolver::PREFIX)->toBe('saas');
});
