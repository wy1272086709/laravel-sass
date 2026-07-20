<?php

/*
|--------------------------------------------------------------------------
| Test Case Bootstrap
|--------------------------------------------------------------------------
|
| 所有 Pest 测试在 Laravel 测试内核中运行（自动引导应用）。
| 阶段 1 测试不依赖业务数据库：纯逻辑 + 真实 Redis + mock 中间件。
|
*/

use Tests\TestCase;
use App\Models\Api\ApiKey;

uses(TestCase::class)->in('Feature', 'Unit', 'Performance');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOneOf', function (array $values) {
    expect($this->value)->toBeIn($values);
});

function signedApiHeaders(ApiKey $apiKey, string $method, string $uri, array $payload = [], ?string $nonce = null, ?string $idempotencyKey = null): array
{
    $timestamp = (string) now()->timestamp;
    $nonce ??= 'nonce-'.str()->random(16);
    $parts = parse_url($uri);
    $query = [];
    parse_str($parts['query'] ?? '', $query);
    ksort($query);
    $canonicalQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $body = json_encode($payload);

    $canonical = implode("\n", [
        strtoupper($method),
        $parts['path'] ?? $uri,
        $canonicalQuery,
        $timestamp,
        $nonce,
        hash('sha256', $body === false ? '' : $body),
    ]);

    $headers = [
        'X-App-Key' => $apiKey->app_key,
        'X-Timestamp' => $timestamp,
        'X-Nonce' => $nonce,
        'X-Signature' => hash_hmac('sha256', $canonical, (string) $apiKey->signing_secret),
    ];

    if ($idempotencyKey !== null) {
        $headers['Idempotency-Key'] = $idempotencyKey;
    }

    return $headers;
}

function signedApiJson(string $method, string $uri, string $token, ApiKey $apiKey, array $payload = [], ?string $idempotencyKey = null, ?string $nonce = null): \Illuminate\Testing\TestResponse
{
    return test()
        ->withToken($token)
        ->withHeaders(signedApiHeaders($apiKey, $method, $uri, $payload, $nonce, $idempotencyKey))
        ->json($method, $uri, $payload);
}
