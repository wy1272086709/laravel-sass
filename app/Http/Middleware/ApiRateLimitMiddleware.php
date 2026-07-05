<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Api\QuotaPolicyService;
use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use App\Infrastructure\Redis\ApiDailyCounter;
use App\Models\Api\ApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 开放 API 配额限流中间件。
 *
 * 流程：取当前租户上下文 → INCR 当日计数 → QuotaPolicyService 判定分级阻断 →
 * 命中返回 429（业务码 42901，data 含 tier/quota/used）。
 *
 * 平台全局视图（tenantId=null）不按租户配额限流。
 *
 * 注：阶段 4 起，开放 API 经 ApiAuthMiddleware 注入 TenantContext（与后台 Session 隔离）。
 */
class ApiRateLimitMiddleware
{
    public function __construct(
        private readonly ApiDailyCounter $counter,
        private readonly QuotaPolicyService $policy,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        // 平台视图或未携带租户：不按配额拦截
        if ($context->tenantId === null) {
            return $next($request);
        }

        $quota = $this->quotaForRequest($request, $context->tier);
        $used = $this->counter->increment($context->tenantId, now()->toDateString());

        if ($this->policy->shouldBlock($context->tier, $used, $quota)) {
            return $this->blockedResponse($context->tier, $quota, $used);
        }

        return $next($request);
    }

    private function quotaForRequest(Request $request, PackageTier $tier): int
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');
        $quota = $apiKey?->tenant()->with('package')->first()?->package?->api_quota_daily;

        if ($quota !== null) {
            return (int) $quota;
        }

        return match ($tier) {
            PackageTier::Basic => 10_000,
            PackageTier::Professional => 100_000,
            PackageTier::Enterprise => 1_000_000,
        };
    }

    private function blockedResponse(PackageTier $tier, int $quota, int $used): JsonResponse
    {
        return response()->json([
            'code' => 42901,
            'message' => 'API daily quota exceeded',
            'data' => [
                'tier' => $tier->value,
                'quota' => $quota,
                'used' => $used,
            ],
        ], 429);
    }
}
