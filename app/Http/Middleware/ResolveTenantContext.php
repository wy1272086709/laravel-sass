<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 解析当前请求的 TenantContext（readonly，请求级绑定）。
 *
 * - merchant guard 命中 → tenantId = merchant_users.tenant_id，套餐取自 tenant.package.tier；
 *   若 session 含 impersonated_by，则为 Impersonation 场景，记录 impersonatorId。
 * - platform guard / 未登录 → tenantId = null（平台全局视图，TenantScope 不附加 where）。
 */
class ResolveTenantContext
{
    public function handle(Request $request, Closure $next): mixed
    {
        $tenantId = null;
        $impersonatorId = null;
        $tier = PackageTier::Basic;

        $merchantUser = Auth::guard('merchant')->user();
        if ($merchantUser !== null) {
            $tenantId = $merchantUser->tenant_id ?? null;
            $merchantUser->loadMissing('tenant.package');
            $tier = $merchantUser->tenant?->package?->tier ?? PackageTier::Basic;
            $impersonatorId = session('impersonated_by');
        }

        app()->instance(
            TenantContext::class,
            new TenantContext($tenantId, $impersonatorId, $tier),
        );

        return $next($request);
    }
}
