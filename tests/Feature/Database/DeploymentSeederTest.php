<?php

use App\Models\Platform\PlatformUser;
use Database\Seeders\DeploymentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds deployment data once and records a completion marker', function () {
    $this->seed(DeploymentSeeder::class);

    $admin = PlatformUser::query()->where('email', 'admin@saas.test')->firstOrFail();

    expect(DB::table('deployment_seed_runs')->where('key', 'default-demo-data-v1')->exists())->toBeTrue();

    $admin->update(['name' => '线上管理员']);

    $this->seed(DeploymentSeeder::class);

    expect($admin->fresh()->name)->toBe('线上管理员');
});
