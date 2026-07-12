<?php

use App\Domain\Enums\RiskLevel;
use App\Models\Billing\ReconciliationDiscrepancy;
use App\Models\Billing\TenantBill;
use App\Models\Platform\PlatformUser;
use App\Models\Risk\RiskAlert;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns risk and reconciliation panel data', function () {
    $user = PlatformUser::factory()->create();
    $tenant = Tenant::factory()->create(['name' => '青禾生活馆']);
    RiskAlert::factory()->create([
        'tenant_id' => $tenant->id,
        'risk_level' => RiskLevel::High,
        'triggered_at' => now(),
    ]);
    $bill = TenantBill::factory()->forTenant($tenant)->create(['billing_period' => '2026-06']);
    ReconciliationDiscrepancy::factory()->create([
        'tenant_id' => $tenant->id,
        'tenant_bill_id' => $bill->id,
        'difference_amount' => 20,
    ]);

    $this->actingAs($user, 'platform')
        ->getJson('/api/internal/platform/risk-recon')
        ->assertOk()
        ->assertJsonPath('data.level_distribution.high', 1)
        ->assertJsonPath('data.recent_alerts.0.tenant_name', '青禾生活馆')
        ->assertJsonPath('data.discrepancies.0.billing_period', '2026-06')
        ->assertJsonStructure([
            'data' => [
                'alert_trend' => ['dates', 'counts'],
                'level_distribution',
                'recent_alerts',
                'discrepancies',
            ],
        ]);
});
