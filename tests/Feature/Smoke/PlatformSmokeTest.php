<?php

use App\Models\Platform\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('opens the critical platform back-office pages', function (string $path) {
    $user = PlatformUser::factory()->create();

    $this->actingAs($user, 'platform')
        ->get($path)
        ->assertOk();
})->with([
    'dashboard' => ['/platform'],
    'tenants' => ['/platform/tenants'],
    'packages' => ['/platform/packages'],
    'roles' => ['/platform/platform-roles'],
    'dashboard panel' => ['/platform/platform-dashboard'],
    'api monitoring panel' => ['/platform/api-monitoring'],
    'queue ops panel' => ['/platform/queue-ops'],
    'risk reconciliation panel' => ['/platform/risk-reconciliation'],
]);
