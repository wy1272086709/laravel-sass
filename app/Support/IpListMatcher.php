<?php

declare(strict_types=1);

namespace App\Support;

/**
 * IP 列表匹配器（网关黑白名单用）。
 *
 * 条目格式：
 * - 精确 IP：`203.0.113.5`（IPv6 仅支持精确匹配）
 * - CIDR：`203.0.113.0/24`（IPv4）
 * - 尾部通配：`203.0.113.*`（按字符串前缀匹配，等价于 203.0.113.0/24 的宽松版）
 *
 * 非法条目（解析失败）直接视为不匹配，不抛异常——名单配置错误不应打挂网关。
 */
final class IpListMatcher
{
    /** IP 是否命中任意一条规则。 */
    public static function inList(string $ip, mixed $entries): bool
    {
        if (! is_iterable($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if (self::matches($ip, (string) $entry)) {
                return true;
            }
        }

        return false;
    }

    /** 单条规则匹配。 */
    public static function matches(string $ip, string $entry): bool
    {
        $entry = trim($entry);

        if ($entry === '' || $ip === '') {
            return false;
        }

        if (str_contains($entry, '*')) {
            return self::matchesWildcard($ip, $entry);
        }

        if (str_contains($entry, '/')) {
            return self::matchesCidr($ip, $entry);
        }

        return strcasecmp($entry, $ip) === 0;
    }

    /** 尾部通配：`203.0.113.*` → 前缀 `203.0.113.` 命中。`*` 单条等价于全放行。 */
    private static function matchesWildcard(string $ip, string $entry): bool
    {
        return str_starts_with($ip, str_replace('*', '', $entry));
    }

    /** IPv4 CIDR：掩码按位与后比较网络号。 */
    private static function matchesCidr(string $ip, string $entry): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $entry, 2), 2, '');

        $ipLong = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? ip2long($ip) : false;
        $subnetLong = filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? ip2long($subnet) : false;

        if ($ipLong === false || $subnetLong === false || ! ctype_digit($bits)) {
            return false;
        }

        $bits = (int) $bits;

        if ($bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits)) & 0xFFFFFFFF;

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
