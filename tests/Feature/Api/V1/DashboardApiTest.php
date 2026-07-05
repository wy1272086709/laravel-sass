<?php

use App\Domain\Enums\ApiPermission;
use App\Domain\Enums\OrderStatus;
use App\Infrastructure\Redis\ApiDailyCounter;
use App\Models\Order\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function () {
    if (isset($this->dashboardTenantId)) {
        app(ApiDailyCounter::class)->reset($this->dashboardTenantId, now()->toDateString());
    }
});

it('returns dashboard overview metrics for the authenticated tenant', function () {
    [$tenant, $token] = apiV1TokenForPermissions([ApiPermission::DashboardRead], quota: 321);
    $this->dashboardTenantId = $tenant->id;
    app(ApiDailyCounter::class)->reset($tenant->id, now()->toDateString());

    Order::factory()->forTenant($tenant)->create(['total_amount' => 100, 'created_at' => now()]);
    Order::factory()->forTenant($tenant)->create(['total_amount' => 50, 'created_at' => now(), 'status' => OrderStatus::RefundRequested]);
    Order::factory()->create(['total_amount' => 999, 'created_at' => now()]);
    app(ApiDailyCounter::class)->increment($tenant->id, now()->toDateString());

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/overview')
        ->assertOk()
        ->assertJsonPath('data.today_orders', 2)
        ->assertJsonPath('data.today_amount', 150)
        ->assertJsonPath('data.refund_rate', 0.5)
        ->assertJsonPath('data.api_calls_today', 2)
        ->assertJsonPath('data.api_quota_daily', 321);
});

it('returns dashboard trends for the requested range', function () {
    [$tenant, $token] = apiV1TokenForPermissions([ApiPermission::DashboardRead]);
    Order::factory()->forTenant($tenant)->create(['total_amount' => 80, 'created_at' => now()->subDay()]);
    Order::factory()->forTenant($tenant)->create(['total_amount' => 20, 'created_at' => now()]);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/trends?days=2')
        ->assertOk()
        ->assertJsonCount(2, 'data.dates')
        ->assertJsonPath('data.order_counts.0', 1)
        ->assertJsonPath('data.order_counts.1', 1)
        ->assertJsonPath('data.amounts.0', 80)
        ->assertJsonPath('data.amounts.1', 20);
});

it('rejects dashboard reads without dashboard_read permission', function () {
    [, $token] = apiV1TokenForPermissions([ApiPermission::BillQuery]);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard/overview')
        ->assertStatus(403)
        ->assertJsonPath('code', 40301);
});
