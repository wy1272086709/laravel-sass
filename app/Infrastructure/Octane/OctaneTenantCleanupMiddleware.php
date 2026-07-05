<?php

declare(strict_types=1);

namespace App\Infrastructure\Octane;

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * 请求结束租户上下文清理（防 Octane/Swoole 常驻进程下租户串号）。
 *
 * 在 finally 中 forgetInstance + 重置为平台全局（tenantId=null）上下文，
 * 确保下一个请求若未重新解析也不会复用上一请求的租户身份。
 * 在 php artisan serve（非 Octane）下同样安全运行。
 */
class OctaneTenantCleanupMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            return $next($request);
        } finally {
            app()->forgetInstance(TenantContext::class);
            app()->instance(
                TenantContext::class,
                new TenantContext(null, null, PackageTier::Basic),
            );
        }
    }
}
