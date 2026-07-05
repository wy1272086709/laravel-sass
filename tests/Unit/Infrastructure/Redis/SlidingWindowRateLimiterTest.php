<?php

use App\Infrastructure\Redis\KeyResolver;
use App\Infrastructure\Redis\SlidingWindowRateLimiter;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->limiter = app(SlidingWindowRateLimiter::class);
    $this->key = KeyResolver::slidingWindow(888001, 'pest', 'qps');
    Redis::del($this->key);
});

afterEach(function () {
    Redis::del($this->key);
});

it('allows up to the limit then blocks', function () {
    expect($this->limiter->tooManyAttempts($this->key, 3, 60))->toBeFalse(); // 1 放行
    expect($this->limiter->tooManyAttempts($this->key, 3, 60))->toBeFalse(); // 2 放行
    expect($this->limiter->tooManyAttempts($this->key, 3, 60))->toBeFalse(); // 3 放行
    expect($this->limiter->tooManyAttempts($this->key, 3, 60))->toBeTrue();  // 4 拦截
});

it('attempt() alias returns true for allowed and false when blocked', function () {
    expect($this->limiter->attempt($this->key, 2, 60))->toBeTrue();
    expect($this->limiter->attempt($this->key, 2, 60))->toBeTrue();
    expect($this->limiter->attempt($this->key, 2, 60))->toBeFalse();
});
