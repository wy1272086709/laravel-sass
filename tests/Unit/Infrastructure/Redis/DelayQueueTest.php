<?php

use App\Infrastructure\Redis\DelayQueue;
use App\Infrastructure\Redis\KeyResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->queue = app(DelayQueue::class);
    $this->name = 'pest-' . uniqid('', false);
    Redis::del(KeyResolver::delayQueue($this->name));
});

afterEach(function () {
    Redis::del(KeyResolver::delayQueue($this->name));
});

it('returns only due payloads and leaves future ones', function () {
    $this->queue->push($this->name, ['id' => 'due'], 0);       // 立即到期
    $this->queue->push($this->name, ['id' => 'future'], 3600);  // 1h 后

    $now = Carbon::now()->getTimestamp();

    expect($this->queue->poll($this->name, $now))->toBe(['id' => 'due'])
        ->and($this->queue->poll($this->name, $now))->toBeNull()  // future 未到期
        ->and($this->queue->size($this->name))->toBe(1);          // future 仍在队列
});
