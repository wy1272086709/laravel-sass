<?php

declare(strict_types=1);

namespace App\Infrastructure\Octane;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SQL 租户越权守卫（SDD §2.4 链路）。
 *
 * 通过 DB::listen 检测针对 tenant 域表的 UPDATE/DELETE 是否缺少 tenant_id 条件，
 * 防止绕过 Eloquent TenantScope 的裸 SQL 造成串号。
 *
 * - 默认仅记录 warning（不破坏平台管理员跨租户的合法批量操作）。
 * - 严格模式（config('saas.sql_tenant_guard_strict') 或 SQL_TENANT_GUARD_STRICT=true）
 *   下抛 RuntimeException，用于 CI/测试提前暴露开发疏漏。
 *
 * 监听器以进程级幂等注册（static flag），Octane 常驻下只挂一次，避免请求间累积。
 */
class SqlTenantGuard
{
    /** tenant 域表（缺 tenant_id 即可疑）。阶段 2 落地全部 16 表后补全。 */
    private const TENANT_TABLES = [
        'merchant_users', 'tenant_settings', 'products', 'product_skus',
        'orders', 'order_items', 'coupons', 'api_keys', 'api_request_logs',
        'api_usage_daily', 'tenant_bills', 'reconciliation_discrepancies',
        'risk_alerts', 'queue_job_logs', 'impersonation_logs',
    ];

    private static bool $listening = false;

    public function handle(Request $request, Closure $next): mixed
    {
        $this->enable();

        return $next($request);
    }

    /**
     * 幂等地挂载 SQL 监听。
     */
    public function enable(): void
    {
        if (self::$listening) {
            return;
        }

        self::$listening = true;

        DB::listen(function (object $query): void {
            $this->inspect((string) $query->sql);
        });
    }

    protected function inspect(string $sql): void
    {
        if (! preg_match('/^\s*(UPDATE|DELETE)\b/i', $sql)) {
            return;
        }

        foreach (self::TENANT_TABLES as $table) {
            if (stripos($sql, $table) === false) {
                continue;
            }

            if (stripos($sql, 'tenant_id') === false) {
                $this->report($table, $sql);
            }
        }
    }

    protected function report(string $table, string $sql): void
    {
        $message = "SqlTenantGuard: tenant 域表 [{$table}] 的裸 SQL 缺少 tenant_id 条件 — {$sql}";

        if ($this->isStrict()) {
            throw new \RuntimeException($message);
        }

        logger()->warning($message);
    }

    protected function isStrict(): bool
    {
        return (bool) (config('saas.sql_tenant_guard_strict', env('SQL_TENANT_GUARD_STRICT', false)));
    }
}
