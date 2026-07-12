<?php

use App\Models\Api\ApiRequestLog;
use App\Models\Platform\PlatformUser;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns api monitoring metrics', function () {
    $user = PlatformUser::factory()->create();
    $tenant = Tenant::factory()->create(['name' => '星河数码']);
    ApiRequestLog::factory()->create([
        'tenant_id' => $tenant->id,
        'status_code' => 200,
        'duration_ms' => 20,
        'requested_at' => now(),
    ]);
    ApiRequestLog::factory()->create([
        'tenant_id' => $tenant->id,
        'status_code' => 500,
        'duration_ms' => 80,
        'requested_at' => now(),
    ]);

    $this->actingAs($user, 'platform')
        ->getJson('/api/internal/platform/api-monitor')
        ->assertOk()
        ->assertJsonPath('data.summary.calls_today', 2)
        ->assertJsonPath('data.summary.errors_today', 1)
        ->assertJsonPath('data.summary.avg_duration_ms', 50)
        ->assertJsonPath('data.top_tenants.0.tenant_name', '星河数码')
        ->assertJsonStructure([
            'data' => [
                'hourly_trend' => ['hours', 'counts', 'errors'],
                'recent_logs',
            ],
        ]);
});
