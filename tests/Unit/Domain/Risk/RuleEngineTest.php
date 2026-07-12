<?php

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\RiskAlertType;
use App\Domain\Enums\RiskLevel;
use App\Domain\Risk\RuleEngine;
use App\Models\Api\ApiUsageDaily;
use App\Models\Billing\TenantBill;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Risk\RiskRule;
use App\Models\Tenant\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('evaluates the five built in risk rules', function () {
    $tenant = Tenant::factory()->create();
    $now = CarbonImmutable::parse('2026-07-05 12:00:00');

    RiskRule::factory()->create([
        'code' => RuleEngine::LARGE_ORDER_AMOUNT,
        'alert_type' => RiskAlertType::BrushOrder,
        'risk_level' => RiskLevel::High,
        'threshold_config' => ['amount' => 1000, 'window_hours' => 24],
    ]);
    RiskRule::factory()->create([
        'code' => RuleEngine::HIGH_REFUND_RATE,
        'alert_type' => RiskAlertType::HighRefundRate,
        'threshold_config' => ['min_orders' => 2, 'rate' => 0.5, 'window_days' => 7],
    ]);
    RiskRule::factory()->create([
        'code' => RuleEngine::API_USAGE_SPIKE,
        'alert_type' => RiskAlertType::AbnormalLogin,
        'threshold_config' => ['min_requests' => 100, 'multiplier' => 2],
    ]);
    RiskRule::factory()->create([
        'code' => RuleEngine::LOW_STOCK_HIGH_SALES,
        'alert_type' => RiskAlertType::BrushOrder,
        'threshold_config' => ['stock_lte' => 5, 'sales_gte' => 100],
    ]);
    RiskRule::factory()->create([
        'code' => RuleEngine::BILLING_DIFFERENCE,
        'alert_type' => RiskAlertType::DuplicatePayment,
        'threshold_config' => ['amount' => 50],
    ]);

    Order::factory()->forTenant($tenant)->create([
        'total_amount' => 1500,
        'status' => OrderStatus::Paid,
        'created_at' => $now->subHour(),
    ]);
    Order::factory()->forTenant($tenant)->create([
        'total_amount' => 100,
        'status' => OrderStatus::RefundRequested,
        'created_at' => $now->subDay(),
    ]);
    Order::factory()->forTenant($tenant)->create([
        'total_amount' => 100,
        'status' => OrderStatus::RefundRequested,
        'created_at' => $now->subDay(),
    ]);
    Order::factory()->forTenant($tenant)->create([
        'total_amount' => 100,
        'status' => OrderStatus::Paid,
        'created_at' => $now->subDay(),
    ]);
    ApiUsageDaily::query()->withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'usage_date' => '2026-07-04',
        'request_count' => 100,
        'overage_count' => 0,
    ]);
    ApiUsageDaily::query()->withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'usage_date' => '2026-07-05',
        'request_count' => 300,
        'overage_count' => 0,
    ]);
    Product::factory()->forTenant($tenant)->create(['stock' => 2, 'sales_count' => 200]);
    TenantBill::factory()->forTenant($tenant)->create(['difference_amount' => 88]);

    $hits = app(RuleEngine::class)->evaluateTenant($tenant, $now);

    expect($hits)->toHaveCount(5)
        ->and($hits->pluck('context.reason')->all())->toContain(
            'large_order_amount',
            'high_refund_rate',
            'api_usage_spike',
            'low_stock_high_sales',
            'billing_difference',
        );
});
