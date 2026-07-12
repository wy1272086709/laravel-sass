<?php

use App\Domain\Enums\QueueJobStatus;
use App\Models\Billing\TenantBill;
use App\Models\Order\Order;
use App\Models\Platform\Package;
use App\Models\Platform\PlatformUser;
use App\Models\System\QueueJobLog;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns platform dashboard metrics for platform users', function () {
    $user = PlatformUser::factory()->create();
    $package = Package::factory()->create(['name' => '专业版']);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    Order::factory()->forTenant($tenant)->create(['total_amount' => 500, 'created_at' => now()]);
    TenantBill::factory()->forTenant($tenant)->create();
    QueueJobLog::factory()->create(['status' => QueueJobStatus::Success]);

    $this->actingAs($user, 'platform')
        ->getJson('/api/internal/platform/dashboard')
        ->assertOk()
        ->assertJsonPath('code', 0)
        ->assertJsonPath('data.summary.tenant_count', fn (int $count): bool => $count >= 1)
        ->assertJsonPath('data.summary.order_count', 1)
        ->assertJsonPath('data.summary.gmv', 500)
        ->assertJsonStructure([
            'data' => [
                'gmv_trend' => ['dates', 'amounts', 'order_counts'],
                'package_distribution',
                'queue_health' => ['success'],
            ],
        ]);
});

it('requires platform auth for dashboard metrics', function () {
    $this->getJson('/api/internal/platform/dashboard')->assertUnauthorized();
});
