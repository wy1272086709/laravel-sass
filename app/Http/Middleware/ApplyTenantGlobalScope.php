<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * 确保 TenantContext 在本请求内已绑定。
 *
 * TenantScope 通过 trait 自动注册为全局 Scope，每次查询时从容器取 TenantContext；
 * 此中间件作为安全网：若上游 ResolveTenantContext 未运行（如 CLI/兜底），绑定一个
 * 平台全局（tenantId=null）上下文，避免 app(TenantContext::class) 解析失败。
 */
class ApplyTenantGlobalScope
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! app()->bound(TenantContext::class)) {
            app()->instance(
                TenantContext::class,
                new TenantContext(null, null, PackageTier::Basic),
            );
        }

        return $next($request);
    }
}
