<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\OrderStatus;
use App\Domain\Tenant\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Infrastructure\Redis\ApiDailyCounter;
use App\Models\Api\ApiKey;
use App\Models\Order\Order;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ApiDailyCounter $counter,
    ) {}

    public function overview(Request $request, TenantContext $context): JsonResponse
    {
        $today = now()->toDateString();
        $todayOrders = Order::query()->whereDate('created_at', $today);
        $totalOrders = Order::query()->count();
        $refundOrders = Order::query()->where('status', OrderStatus::RefundRequested)->count();

        return ApiResponse::ok([
            'today_orders' => (clone $todayOrders)->count(),
            'today_amount' => (float) (clone $todayOrders)->sum('total_amount'),
            'refund_rate' => $totalOrders === 0 ? 0.0 : round($refundOrders / $totalOrders, 4),
            'api_calls_today' => $context->tenantId === null ? 0 : $this->counter->get($context->tenantId, $today),
            'api_quota_daily' => $this->quotaForRequest($request),
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        $data = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);

        $days = (int) ($data['days'] ?? 7);
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        $ordersByDate = Order::query()
            ->selectRaw('DATE(created_at) as order_date, COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as amount')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('order_date')
            ->get()
            ->keyBy('order_date');

        $dates = collect(CarbonPeriod::create($start, $end))
            ->map(fn ($date): string => $date->toDateString())
            ->values();

        return ApiResponse::ok([
            'dates' => $dates->all(),
            'order_counts' => $dates->map(fn (string $date): int => (int) ($ordersByDate->get($date)?->order_count ?? 0))->all(),
            'amounts' => $dates->map(fn (string $date): float => (float) ($ordersByDate->get($date)?->amount ?? 0))->all(),
        ]);
    }

    private function quotaForRequest(Request $request): int
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        return (int) ($apiKey?->tenant()->with('package')->first()?->package?->api_quota_daily ?? 0);
    }
}
