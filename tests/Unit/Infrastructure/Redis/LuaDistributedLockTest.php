<?php

use App\Infrastructure\Redis\KeyResolver;
use App\Infrastructure\Redis\LuaDistributedLock;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->lock = app(LuaDistributedLock::class);
    $this->module = 'pest';
    $this->id = 'lock-' . uniqid('', false);
});

afterEach(function () {
    Redis::del(KeyResolver::lock($this->module, $this->id));
});

it('acquires exclusively and releases only with the correct token', function () {
    $token = $this->lock->acquire($this->module, $this->id, 10_000);
    expect($token)->not->toBeNull('first acquire should succeed');

    // 同 key 第二次获取被互斥
    expect($this->lock->acquire($this->module, $this->id, 10_000))->toBeNull();

    // 错误 token 不释放
    expect($this->lock->release($this->module, $this->id, 'bogus-token'))->toBeFalse();

    // 正确 token 释放
    expect($this->lock->release($this->module, $this->id, $token))->toBeTrue();

    // 释放后可再次获取
    expect($this->lock->acquire($this->module, $this->id, 10_000))->not->toBeNull();
});
