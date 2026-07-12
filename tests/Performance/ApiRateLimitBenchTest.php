<?php

use App\Domain\Enums\ApiPermission;
use App\Infrastructure\Redis\ApiDailyCounter;
use App\Models\Api\ApiKey;
use App\Models\Platform\Package;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function () {
    if (isset($this->benchTenantId)) {
        app(ApiDailyCounter::class)->reset($this->benchTenantId, now()->toDateString());
    }
});

it('records a repeatable api rate-limit baseline', function () {
    $package = Package::factory()->create(['api_quota_daily' => 5000]);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    $this->benchTenantId = $tenant->id;
    app(ApiDailyCounter::class)->reset($tenant->id, now()->toDateString());

    $apiKey = ApiKey::factory()->forTenant($tenant)->create([
        'app_key' => 'AK_BENCH_'.str()->random(8),
        'app_secret' => Hash::make('plain-secret'),
        'permissions' => [ApiPermission::ProductQuery],
    ]);
    Product::factory()->forTenant($tenant)->count(3)->create();

    $token = $this->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'plain-secret',
    ])->assertOk()->json('data.access_token');

    $runs = 25;
    $startedAt = hrtime(true);

    for ($i = 0; $i < $runs; $i++) {
        $this->withToken($token)
            ->getJson('/api/v1/products?per_page=3')
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;
    $averageMs = round($elapsedMs / $runs, 2);

    test()->note(sprintf('Stage 7 API rate-limit baseline: %d requests, %.2f ms avg', $runs, $averageMs));

    expect(app(ApiDailyCounter::class)->get($tenant->id, now()->toDateString()))->toBe($runs)
        ->and($averageMs)->toBeGreaterThan(0.0);
});
