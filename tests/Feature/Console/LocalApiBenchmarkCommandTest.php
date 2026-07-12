<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('runs the local api benchmark command against a mocked local server', function () {
    Http::fake([
        'http://127.0.0.1:8000/api/v1/auth/token' => Http::response([
            'code' => 0,
            'message' => 'ok',
            'data' => [
                'access_token' => 'bench-token',
            ],
        ]),
        'http://127.0.0.1:8000/api/v1/products' => Http::response([
            'code' => 0,
            'message' => 'ok',
            'data' => [],
        ]),
    ]);

    $this->artisan('benchmark:local-api', ['--requests' => 3])
        ->expectsOutputToContain('Benchmarking http://127.0.0.1:8000/api/v1/products')
        ->assertSuccessful();

    Http::assertSentCount(4);
});

it('fails clearly when token exchange fails', function () {
    Http::fake([
        'http://127.0.0.1:8000/api/v1/auth/token' => Http::response([
            'code' => 40101,
            'message' => 'Invalid API credentials',
            'data' => null,
        ], 401),
    ]);

    $this->artisan('benchmark:local-api')
        ->expectsOutputToContain('Unable to issue benchmark access token.')
        ->assertFailed();
});
