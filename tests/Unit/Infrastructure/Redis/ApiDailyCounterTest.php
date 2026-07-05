<?php

use App\Infrastructure\Redis\ApiDailyCounter;

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
