<?php

use App\Domain\Enums\OrderStatus;
use App\Domain\Enums\RiskAlertStatus;
use App\Domain\Enums\RiskAlertType;
use App\Domain\Enums\RiskLevel;
use App\Domain\Risk\RuleEngine;
use App\Jobs\RiskRuleScanJob;
use App\Models\Order\Order;
use App\Models\Risk\RiskAlert;
use App\Models\Risk\RiskRule;
use App\Models\System\QueueJobLog;
use App\Models\Tenant\Tenant;
use App\Support\QueueJobLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates risk alerts and avoids duplicate pending alerts', function () {
    $tenant = Tenant::factory()->create();
    RiskRule::factory()->create([
        'code' => RuleEngine::LARGE_ORDER_AMOUNT,
        'alert_type' => RiskAlertType::BrushOrder,
        'risk_level' => RiskLevel::High,
        'threshold_config' => ['amount' => 1000, 'window_hours' => 24],
    ]);
    Order::factory()->forTenant($tenant)->create([
        'total_amount' => 5000,
        'status' => OrderStatus::Paid,
        'created_at' => now()->subHour(),
    ]);

    $job = app(RiskRuleScanJob::class);
    $job->handle(app(RuleEngine::class), app(QueueJobLogger::class));
    $job->handle(app(RuleEngine::class), app(QueueJobLogger::class));

    expect(RiskAlert::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(RiskAlert::query()->withoutGlobalScopes()->first()->status)->toBe(RiskAlertStatus::Pending)
        ->and(QueueJobLog::query()->withoutGlobalScopes()->where('name', RiskRuleScanJob::class)->count())->toBe(2);
});
