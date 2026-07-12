<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Domain\Enums\BillStatus;
use App\Domain\Enums\QueueJobStatus;
use App\Http\Controllers\Controller;
use App\Models\Billing\TenantBill;
use App\Models\Order\Order;
use App\Models\Platform\Package;
use App\Models\System\QueueJobLog;
use App\Models\Tenant\Tenant;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;

class PlatformDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $start = now()->subDays(6)->startOfDay();
        $end = now()->endOfDay();

        $ordersByDate = Order::query()
            ->withoutGlobalScopes()
            ->selectRaw('DATE(created_at) as order_date, COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as amount')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('order_date')
            ->get()
            ->keyBy('order_date');

        $dates = collect(CarbonPeriod::create($start, $end))
            ->map(fn ($date): string => $date->toDateString())
            ->values();

        $queueCounts = QueueJobLog::query()
            ->withoutGlobalScopes()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'summary' => [
                    'tenant_count' => Tenant::query()->count(),
                    'order_count' => Order::query()->withoutGlobalScopes()->count(),
                    'gmv' => (float) Order::query()->withoutGlobalScopes()->sum('total_amount'),
                    'pending_bills' => TenantBill::query()->withoutGlobalScopes()->where('status', BillStatus::PendingSettlement)->count(),
                ],
                'gmv_trend' => [
                    'dates' => $dates->all(),
                    'amounts' => $dates->map(fn (string $date): float => (float) ($ordersByDate->get($date)?->amount ?? 0))->all(),
                    'order_counts' => $dates->map(fn (string $date): int => (int) ($ordersByDate->get($date)?->order_count ?? 0))->all(),
                ],
                'package_distribution' => Package::query()
                    ->withCount('tenants')
                    ->get()
                    ->map(fn (Package $package): array => [
                        'tier' => $package->tier->value,
                        'name' => $package->name,
                        'tenant_count' => $package->tenants_count,
                    ])
                    ->values()
                    ->all(),
                'queue_health' => collect(QueueJobStatus::cases())
                    ->mapWithKeys(fn (QueueJobStatus $status): array => [$status->value => (int) ($queueCounts[$status->value] ?? 0)])
                    ->all(),
            ],
        ]);
    }
}
