<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Api\ApiKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class ApiSecurityDemoSeeder extends Seeder
{
    public function run(): void
    {
        ApiKey::query()
            ->withoutGlobalScopes()
            ->orderBy('id')
            ->get()
            ->each(function (ApiKey $apiKey): void {
                $this->seedSignatureNonces($apiKey);
                $this->seedIdempotencyKeys($apiKey);
                $this->seedPersonalAccessTokens($apiKey);
            });
    }

    private function seedSignatureNonces(ApiKey $apiKey): void
    {
        foreach ([
            ['suffix' => 'active', 'expires_at' => now()->addMinutes(5)],
            ['suffix' => 'expired', 'expires_at' => now()->subMinutes(5)],
        ] as $record) {
            DB::table('api_signature_nonces')->updateOrInsert(
                [
                    'api_key_id' => $apiKey->id,
                    'nonce' => sprintf('demo-nonce-%d-%s', $apiKey->id, $record['suffix']),
                ],
                [
                    'tenant_id' => $apiKey->tenant_id,
                    'expires_at' => $record['expires_at'],
                    'created_at' => now()->subMinute(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function seedIdempotencyKeys(ApiKey $apiKey): void
    {
        $records = [
            [
                'suffix' => 'completed',
                'endpoint' => '/api/v1/orders',
                'request_hash' => hash('sha256', "demo-order-{$apiKey->id}"),
                'status_code' => 201,
                'response_body' => json_encode([
                    'code' => 0,
                    'message' => 'Created from demo idempotency request',
                    'data' => ['order_no' => sprintf('DEMO-%06d', $apiKey->id)],
                ], JSON_UNESCAPED_SLASHES),
            ],
            [
                'suffix' => 'processing',
                'endpoint' => '/api/v1/orders/cancel',
                'request_hash' => hash('sha256', "demo-cancel-{$apiKey->id}"),
                'status_code' => null,
                'response_body' => null,
            ],
        ];

        foreach ($records as $record) {
            DB::table('api_idempotency_keys')->updateOrInsert(
                [
                    'tenant_id' => $apiKey->tenant_id,
                    'idempotency_key' => sprintf('demo-idempotency-%d-%s', $apiKey->id, $record['suffix']),
                ],
                [
                    'api_key_id' => $apiKey->id,
                    'method' => 'POST',
                    'endpoint' => $record['endpoint'],
                    'request_hash' => $record['request_hash'],
                    'status_code' => $record['status_code'],
                    'response_body' => $record['response_body'],
                    'expires_at' => now()->addDay(),
                    'created_at' => now()->subMinutes(2),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function seedPersonalAccessTokens(ApiKey $apiKey): void
    {
        $abilities = collect($apiKey->permissions)
            ->map(fn (mixed $permission): string => is_object($permission) && property_exists($permission, 'value')
                ? (string) $permission->value
                : (string) $permission)
            ->values()
            ->all();

        foreach ([
            ['name' => 'demo-api-access', 'abilities' => $abilities, 'expires_at' => now()->addHours(2)],
            ['name' => 'demo-api-refresh', 'abilities' => ['refresh'], 'expires_at' => now()->addDays(30)],
        ] as $record) {
            PersonalAccessToken::query()->updateOrCreate(
                [
                    'tokenable_type' => ApiKey::class,
                    'tokenable_id' => $apiKey->id,
                    'name' => $record['name'],
                ],
                [
                    'token' => hash('sha256', sprintf('%s:%d:local-demo-token', $record['name'], $apiKey->id)),
                    'abilities' => $record['abilities'],
                    'last_used_at' => $record['name'] === 'demo-api-access' ? now()->subMinutes(10) : null,
                    'expires_at' => $record['expires_at'],
                ],
            );
        }
    }
}
