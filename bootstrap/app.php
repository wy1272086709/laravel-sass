<?php

use App\Http\Middleware\ApiRateLimitMiddleware;
use App\Http\Middleware\ApplyTenantGlobalScope;
use App\Http\Middleware\ResolveTenantContext;
use App\Infrastructure\Octane\OctaneTenantCleanupMiddleware;
use App\Infrastructure\Octane\SqlTenantGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // —— 中间件别名（可在路由中按名引用） ——
        $middleware->alias([
            'resolve.tenant' => ResolveTenantContext::class,
            'tenant.scope' => ApplyTenantGlobalScope::class,
            'api.rate' => ApiRateLimitMiddleware::class,
            'sql.tenant.guard' => SqlTenantGuard::class,
            'octane.tenant.cleanup' => OctaneTenantCleanupMiddleware::class,
        ]);

        // —— 租户上下文链路（web + api 组），顺序：
        //    ResolveTenantContext → ApplyTenantGlobalScope → SqlTenantGuard → [业务]
        $tenantChain = [
            ResolveTenantContext::class,
            ApplyTenantGlobalScope::class,
            SqlTenantGuard::class,
        ];

        foreach (['web', 'api'] as $group) {
            $middleware->appendToGroup($group, $tenantChain);
            // 清理放在链尾，finally 在响应后重置上下文
            $middleware->appendToGroup($group, OctaneTenantCleanupMiddleware::class);
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
