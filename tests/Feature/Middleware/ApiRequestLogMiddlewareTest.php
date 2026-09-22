<?php

use App\Domain\Enums\ApiPermission;
use App\Http\Middleware\ApiRateLimitMiddleware;
use App\Jobs\LogApiRequestJob;
use App\Models\Api\ApiKey;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ApiRateLimitMiddleware::class);
});

it('dispatches a request log job with full tracing payload', function () {
    [$tenant, $token, $apiKey] = requestLogToken();

    Queue::fake();

    $response = $this->withToken($token)->getJson('/api/v1/products')
        ->assertOk();

    Queue::assertPushed(LogApiRequestJob::class, function (LogApiRequestJob $job) use ($tenant, $apiKey, $response): bool {
        $log = $job->log;

        return $log['tenant_id'] === $tenant->id
            && $log['api_key_id'] === $apiKey->id
            && $log['method'] === 'GET'
            && $log['endpoint'] === '/api/v1/products'
            && $log['status_code'] === 200
            && is_int($log['duration_ms'])
            && $log['ip_address'] === '127.0.0.1'
            && $log['request_id'] === $response->headers->get('X-Request-Id');
    });
});

it('propagates a valid caller-supplied request id', function () {
    [, $token] = requestLogToken();

    Queue::fake();

    $response = $this->withToken($token)
        ->withHeader('X-Request-Id', 'trace-20260922-0001')
        ->getJson('/api/v1/products')
        ->assertOk();

    expect($response->headers->get('X-Request-Id'))->toBe('trace-20260922-0001');

    Queue::assertPushed(LogApiRequestJob::class, fn (LogApiRequestJob $job): bool => $job->log['request_id'] === 'trace-20260922-0001');
});

it('replaces an invalid caller-supplied request id with a uuid', function () {
    [, $token] = requestLogToken();

    Queue::fake();

    $response = $this->withToken($token)
        ->withHeader('X-Request-Id', 'not a valid id!')
        ->getJson('/api/v1/products')
        ->assertOk();

    expect($response->headers->get('X-Request-Id'))
        ->not->toBe('not a valid id!')
        ->toBeString();

    Queue::assertPushed(LogApiRequestJob::class, fn (LogApiRequestJob $job): bool => $job->log['request_id'] === $response->headers->get('X-Request-Id'));
});

it('logs rejected unauthenticated requests with a null tenant', function () {
    requestLogToken();

    Queue::fake();

    $this->getJson('/api/v1/products')
        ->assertStatus(401)
        ->assertJsonPath('code', 40101);

    Queue::assertPushed(LogApiRequestJob::class, function (LogApiRequestJob $job): bool {
        $log = $job->log;

        return $log['tenant_id'] === null
            && $log['api_key_id'] === null
            && $log['status_code'] === 401;
    });
});

it('logs requests rejected by the ip filter', function () {
    [, $token] = requestLogToken(blacklist: ['127.0.0.1']);

    Queue::fake();

    $this->withToken($token)->getJson('/api/v1/products')
        ->assertStatus(403)
        ->assertJsonPath('code', 40302);

    Queue::assertPushed(LogApiRequestJob::class, fn (LogApiRequestJob $job): bool => $job->log['status_code'] === 403);
});

it('skips the ping health probe', function () {
    [, $token] = requestLogToken();

    Queue::fake();

    $this->withToken($token)->getJson('/api/v1/ping')->assertOk();

    Queue::assertNotPushed(LogApiRequestJob::class);
});

/**
 * @param  array<int, string>|null  $blacklist
 * @return array{0: Tenant, 1: string, 2: ApiKey}
 */
function requestLogToken(?array $blacklist = null): array
{
    $package = Package::factory()->create(['api_quota_daily' => 1000]);
    $tenant = Tenant::factory()->create(['package_id' => $package->id]);
    $apiKey = ApiKey::factory()->forTenant($tenant)->create([
        'app_key' => 'AK_REQLOG_'.str()->random(8),
        'app_secret' => Hash::make('plain-secret'),
        'permissions' => [ApiPermission::ProductQuery],
        'ip_blacklist' => $blacklist,
    ]);

    $response = test()->postJson('/api/v1/auth/token', [
        'app_key' => $apiKey->app_key,
        'app_secret' => 'plain-secret',
    ])->assertOk();

    return [$tenant, $response->json('data.access_token'), $apiKey];
}
