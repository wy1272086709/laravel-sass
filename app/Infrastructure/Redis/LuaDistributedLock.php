<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Illuminate\Support\Facades\Redis;

/**
 * Redis Lua 原子分布式锁（SET NX PX + token 释放）。
 *
 * 典型用途：月结任务互斥、防重复结算。获取方持 token，释放时 Lua 比对 token
 * 仅删除自己持有的锁，避免误释放他人。
 *
 * 用法：
 *   $token = $lock->acquire('billing', '2026-06', 60_000);
 *   if ($token === null) { /* 未抢到锁 *\/ }
 *   try { /* 临界区 *\/ } finally { $lock->release($key, $token); }
 */
final class LuaDistributedLock
{
    /** @var string SET key token NX PX ttl —— 原子获取，失败返回 nil */
    private const ACQUIRE_LUA = <<<'LUA'
        return redis.call("SET", KEYS[1], ARGV[1], "NX", "PX", ARGV[2])
    LUA;

    /** @var string 仅当 token 匹配才 DEL，避免误删 */
    private const RELEASE_LUA = <<<'LUA'
        if redis.call("GET", KEYS[1]) == ARGV[1] then
            return redis.call("DEL", KEYS[1])
        else
            return 0
        end
    LUA;

    /**
     * 尝试获取锁。
     *
     * @return string|null 锁 token（成功），null（已被占用）。
     */
    public function acquire(string $module, string $identifier, int $ttlMs): ?string
    {
        $key = KeyResolver::lock($module, $identifier);
        $token = bin2hex(random_bytes(16));

        $result = Redis::eval(self::ACQUIRE_LUA, 1, $key, $token, (string) $ttlMs);

        return $result ? $token : null;
    }

    /**
     * 释放锁（仅当持有 token）。
     */
    public function release(string $module, string $identifier, string $token): bool
    {
        $key = KeyResolver::lock($module, $identifier);

        return (bool) Redis::eval(self::RELEASE_LUA, 1, $key, $token);
    }
}
