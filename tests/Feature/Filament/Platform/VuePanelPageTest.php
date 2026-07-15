<?php

use App\Models\Platform\PlatformUser;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(Vite::class)->useHotFile(storage_path('framework/testing/vite.hot'));
});

afterEach(function () {
    app(Vite::class)->useHotFile(public_path('hot'));
});

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
