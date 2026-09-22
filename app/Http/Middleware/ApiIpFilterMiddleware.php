<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Api\ApiKey;
use App\Support\IpListMatcher;
use Closure;
use Illuminate\Http\Request;

/**
 * 开放 API IP 黑白名单中间件（挂在 api.auth 之后，按密钥维度过滤）。
 *
 * 判定顺序：黑名单命中 → 403（40302）；白名单非空且未命中 → 403（40303）。
 * 两个列表均未配置时不拦截。名单存于 api_keys 表 JSON 列，商户后台可自助维护。
 */
class ApiIpFilterMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey instanceof ApiKey) {
            $ip = (string) $request->ip();

            $blacklist = $apiKey->ip_blacklist ?? [];

            if (IpListMatcher::inList($ip, $blacklist)) {
                return ApiResponse::error(40302, 'Client IP is blacklisted for this API key', 403);
            }

            $whitelist = $apiKey->ip_whitelist ?? [];

            if ($whitelist !== [] && ! IpListMatcher::inList($ip, $whitelist)) {
                return ApiResponse::error(40303, 'Client IP is not in the API key whitelist', 403);
            }
        }

        return $next($request);
    }
}
