<?php

use App\Jobs\LogApiRequestJob;
use App\Models\Api\ApiKey;
use App\Models\Api\ApiRequestLog;
use App\Models\Tenant\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists a tenant request log row', function () {
    $tenant = Tenant::factory()->create();
    $apiKey = ApiKey::factory()->forTenant($tenant)->create();

    (new LogApiRequestJob([
        'tenant_id' => $tenant->id,
        'api_key_id' => $apiKey->id,
        'request_id' => 'trace-20260922-0100',
        'method' => 'POST',
        'endpoint' => '/api/v1/orders',
        'status_code' => 201,
        'duration_ms' => 42,
        'ip_address' => '203.0.113.5',
        'requested_at' => now()->toDateTimeString(),
    ]))->handle();

    $log = ApiRequestLog::query()->withoutGlobalScopes()->firstOrFail();

    expect($log->tenant_id)->toBe($tenant->id)
        ->and($log->api_key_id)->toBe($apiKey->id)
        ->and($log->request_id)->toBe('trace-20260922-0100')
        ->and($log->endpoint)->toBe('/api/v1/orders')
        ->and($log->status_code)->toBe(201)
        ->and($log->duration_ms)->toBe(42);
});

it('persists tenant-less rows for unauthenticated gateway calls', function () {
    (new LogApiRequestJob([
        'tenant_id' => null,
        'api_key_id' => null,
        'request_id' => 'trace-20260922-0200',
        'method' => 'POST',
        'endpoint' => '/api/v1/auth/token',
        'status_code' => 401,
        'duration_ms' => 5,
        'ip_address' => '198.51.100.9',
        'requested_at' => now()->toDateTimeString(),
    ]))->handle();

    expect(ApiRequestLog::query()->withoutGlobalScopes()->whereNull('tenant_id')->count())->toBe(1);
});
