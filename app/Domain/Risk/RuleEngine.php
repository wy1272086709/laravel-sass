<?php

declare(strict_types=1);

namespace App\Domain\Risk;

use App\Domain\Enums\OrderStatus;
use App\Models\Api\ApiUsageDaily;
use App\Models\Billing\TenantBill;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Risk\RiskRule;
use App\Models\Tenant\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class RuleEngine
{
    public const LARGE_ORDER_AMOUNT = 'large_order_amount';

    public const HIGH_REFUND_RATE = 'high_refund_rate';

    public const API_USAGE_SPIKE = 'api_usage_spike';

    public const LOW_STOCK_HIGH_SALES = 'low_stock_high_sales';

    public const BILLING_DIFFERENCE = 'billing_difference';

    /** @return Collection<int, array<string, mixed>> */
    public function evaluateTenant(Tenant $tenant, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();

        return RiskRule::query()
            ->where('is_active', true)
            ->get()
            ->flatMap(fn (RiskRule $rule): Collection => $this->evaluateRule($tenant, $rule, $now))
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function evaluateRule(Tenant $tenant, RiskRule $rule, CarbonImmutable $now): Collection
    {
        $config = $rule->threshold_config ?? [];

        return match ($rule->code) {
            self::LARGE_ORDER_AMOUNT => $this->largeOrders($tenant, $rule, $config, $now),
            self::HIGH_REFUND_RATE => $this->highRefundRate($tenant, $rule, $config, $now),
            self::API_USAGE_SPIKE => $this->apiUsageSpike($tenant, $rule, $config, $now),
            self::LOW_STOCK_HIGH_SALES => $this->lowStockHighSales($tenant, $rule, $config),
            self::BILLING_DIFFERENCE => $this->billingDifference($tenant, $rule, $config),
            default => collect(),
        };
    }

    /** @param array<string, mixed> $config */
    private function largeOrders(Tenant $tenant, RiskRule $rule, array $config, CarbonImmutable $now): Collection
    {
        $amount = (float) ($config['amount'] ?? 10000);
        $hours = (int) ($config['window_hours'] ?? 24);

        return Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('total_amount', '>=', $amount)
            ->where('created_at', '>=', $now->subHours($hours))
            ->get()
            ->map(fn (Order $order): array => $this->hit($tenant, $rule, [
                'reason' => 'large_order_amount',
                'order_no' => $order->order_no,
                'amount' => (float) $order->total_amount,
                'threshold' => $amount,
            ], $order->id));
    }

    /** @param array<string, mixed> $config */
    private function highRefundRate(Tenant $tenant, RiskRule $rule, array $config, CarbonImmutable $now): Collection
    {
        $days = (int) ($config['window_days'] ?? 7);
        $minOrders = (int) ($config['min_orders'] ?? 10);
        $rateThreshold = (float) ($config['rate'] ?? 0.2);

        $orders = Order::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $now->subDays($days));

        $total = (clone $orders)->count();

        if ($total < $minOrders) {
            return collect();
        }

        $refunds = (clone $orders)->where('status', OrderStatus::RefundRequested)->count();
        $rate = $total === 0 ? 0 : $refunds / $total;

        if ($rate < $rateThreshold) {
            return collect();
        }

        return collect([$this->hit($tenant, $rule, [
            'reason' => 'high_refund_rate',
            'total_orders' => $total,
            'refund_orders' => $refunds,
            'refund_rate' => round($rate, 4),
            'threshold' => $rateThreshold,
        ])]);
    }

    /** @param array<string, mixed> $config */
    private function apiUsageSpike(Tenant $tenant, RiskRule $rule, array $config, CarbonImmutable $now): Collection
    {
        $multiplier = (float) ($config['multiplier'] ?? 2.0);
        $minRequests = (int) ($config['min_requests'] ?? 1000);
        $today = $now->toDateString();

        $todayUsage = (int) ApiUsageDaily::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereDate('usage_date', $today)
            ->sum('request_count');

        if ($todayUsage < $minRequests) {
            return collect();
        }

        $baseline = (float) ApiUsageDaily::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereDate('usage_date', '<', $today)
            ->whereDate('usage_date', '>=', $now->subDays(7)->toDateString())
            ->avg('request_count');

        if ($baseline <= 0 || $todayUsage < $baseline * $multiplier) {
            return collect();
        }

        return collect([$this->hit($tenant, $rule, [
            'reason' => 'api_usage_spike',
            'request_count' => $todayUsage,
            'baseline' => round($baseline, 2),
            'multiplier' => $multiplier,
        ])]);
    }

    /** @param array<string, mixed> $config */
    private function lowStockHighSales(Tenant $tenant, RiskRule $rule, array $config): Collection
    {
        $stock = (int) ($config['stock_lte'] ?? 5);
        $sales = (int) ($config['sales_gte'] ?? 100);

        return Product::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('stock', '<=', $stock)
            ->where('sales_count', '>=', $sales)
            ->get()
            ->map(fn (Product $product): array => $this->hit($tenant, $rule, [
                'reason' => 'low_stock_high_sales',
                'product_id' => $product->id,
                'product_name' => $product->name,
                'stock' => $product->stock,
                'sales_count' => $product->sales_count,
            ]));
    }

    /** @param array<string, mixed> $config */
    private function billingDifference(Tenant $tenant, RiskRule $rule, array $config): Collection
    {
        $amount = (float) ($config['amount'] ?? 100);

        return TenantBill::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('difference_amount')
            ->get()
            ->filter(fn (TenantBill $bill): bool => abs((float) $bill->difference_amount) >= $amount)
            ->map(fn (TenantBill $bill): array => $this->hit($tenant, $rule, [
                'reason' => 'billing_difference',
                'billing_period' => $bill->billing_period,
                'difference_amount' => (float) $bill->difference_amount,
                'threshold' => $amount,
            ]));
    }

    /** @param array<string, mixed> $context */
    private function hit(Tenant $tenant, RiskRule $rule, array $context, ?int $orderId = null): array
    {
        return [
            'tenant_id' => $tenant->id,
            'type' => $rule->alert_type,
            'risk_level' => $rule->risk_level,
            'context' => [
                'rule_code' => $rule->code,
                'rule_name' => $rule->name,
                ...$context,
            ],
            'order_id' => $orderId,
        ];
    }
}
