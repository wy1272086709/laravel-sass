<?php

use App\Models\Platform\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the platform vue panel pages with their vite entries', function (string $path, string $mountId) {
    $user = PlatformUser::factory()->create();

    $this->actingAs($user, 'platform')
        ->get($path)
        ->assertOk()
        ->assertSee($mountId)
        ->assertSee('/build/assets/');
})->with([
    ['/platform/platform-dashboard', 'platform-dashboard-panel'],
    ['/platform/api-monitoring', 'api-monitoring-panel'],
    ['/platform/queue-ops', 'queue-ops-panel'],
    ['/platform/risk-reconciliation', 'risk-reconciliation-panel'],
]);
