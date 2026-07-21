<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiRequestLog;
use App\Models\Tenant\Tenant;
use Illuminate\Http\JsonResponse;

class ApiMonitoringController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $today = now()->startOfDay();
        $hours = collect(range(23, 0))
            ->map(fn (int $offset) => now()->subHours($offset)->startOfHour())
            ->values();
        $driver = ApiRequestLog::query()->getConnection()->getDriverName();
        $hourExpression = match ($driver) {
            'mysql', 'mariadb' => "DATE_FORMAT(requested_at, '%Y-%m-%d %H:00')",
            'pgsql' => "TO_CHAR(requested_at, 'YYYY-MM-DD HH24:00')",
            default => "strftime('%Y-%m-%d %H:00', requested_at)",
        };

        $logsByHour = ApiRequestLog::query()
            ->withoutGlobalScopes()
            ->selectRaw($hourExpression.' as hour_key, COUNT(*) as total, SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors')
            ->where('requested_at', '>=', now()->subHours(23)->startOfHour())
            ->groupBy('hour_key')
            ->get()
            ->keyBy('hour_key');

        $callsToday = ApiRequestLog::query()->withoutGlobalScopes()->where('requested_at', '>=', $today)->count();
        $errorsToday = ApiRequestLog::query()->withoutGlobalScopes()->where('requested_at', '>=', $today)->where('status_code', '>=', 400)->count();

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'summary' => [
                    'calls_today' => $callsToday,
                    'errors_today' => $errorsToday,
                    'avg_duration_ms' => (int) round((float) ApiRequestLog::query()->withoutGlobalScopes()->where('requested_at', '>=', $today)->avg('duration_ms')),
                ],
                'hourly_trend' => [
                    'hours' => $hours->map(fn ($hour): string => $hour->format('H:00'))->all(),
                    'counts' => $hours->map(fn ($hour): int => (int) ($logsByHour->get($hour->format('Y-m-d H:00'))?->total ?? 0))->all(),
                    'errors' => $hours->map(fn ($hour): int => (int) ($logsByHour->get($hour->format('Y-m-d H:00'))?->errors ?? 0))->all(),
                ],
                'top_tenants' => Tenant::query()
                    ->select('tenants.id', 'tenants.name')
                    ->join('api_request_logs', 'api_request_logs.tenant_id', '=', 'tenants.id')
                    ->selectRaw('COUNT(api_request_logs.id) as request_count')
                    ->groupBy('tenants.id', 'tenants.name')
                    ->orderByDesc('request_count')
                    ->limit(10)
                    ->get()
                    ->map(fn (Tenant $tenant): array => [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'request_count' => (int) $tenant->request_count,
                    ])
                    ->all(),
                'recent_logs' => ApiRequestLog::query()
                    ->withoutGlobalScopes()
                    ->with('tenant')
                    ->latest('requested_at')
                    ->limit(10)
                    ->get()
                    ->map(fn (ApiRequestLog $log): array => [
                        'request_id' => $log->request_id,
                        'tenant_name' => $log->tenant?->name,
                        'method' => $log->method,
                        'endpoint' => $log->endpoint,
                        'status_code' => $log->status_code,
                        'duration_ms' => $log->duration_ms,
                        'requested_at' => $log->requested_at?->toJSON(),
                    ])
                    ->all(),
            ],
        ]);
    }
}
