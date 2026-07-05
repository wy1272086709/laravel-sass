<?php

use App\Infrastructure\Redis\DeadLetterQueue;
use App\Infrastructure\Redis\KeyResolver;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->dlq = app(DeadLetterQueue::class);
    $this->name = 'pest-' . uniqid('', false);
    Redis::del(KeyResolver::deadLetterQueue($this->name));
});

afterEach(function () {
    Redis::del(KeyResolver::deadLetterQueue($this->name));
});

it('stores failed jobs with reason and timestamp and lists them', function () {
    expect($this->dlq->size($this->name))->toBe(0);

    $this->dlq->push($this->name, ['job' => 'CloseExpiredOrder', 'order_id' => 1], 'timeout');
    $this->dlq->push($this->name, ['job' => 'SyncLogistics', 'order_id' => 2], 'http_5xx');

    $list = $this->dlq->list($this->name);

    expect($list)->toHaveCount(2)
        ->and($list[0]['reason'])->toBe('timeout')
        ->and($list[0]['payload'])->toMatchArray(['job' => 'CloseExpiredOrder', 'order_id' => 1])
        ->and($list[0]['failed_at'])->toBeString()
        ->and($list[1]['reason'])->toBe('http_5xx')
        ->and($this->dlq->size($this->name))->toBe(2);
});
