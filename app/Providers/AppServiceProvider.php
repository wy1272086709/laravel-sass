<?php

namespace App\Providers;

use App\Domain\Enums\PackageTier;
use App\Domain\Tenant\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 默认租户上下文：平台全局视图（tenantId=null）。
        // HTTP 请求由 ResolveTenantContext 覆盖；CLI / 兜底场景使用此默认，
        // OctaneTenantCleanupMiddleware 在请求结束后重置回此值。
        $this->app->singleton(
            TenantContext::class,
            fn (): TenantContext => new TenantContext(null, null, PackageTier::Basic),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
