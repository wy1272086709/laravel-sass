<?php

namespace Database\Factories\Api;

use App\Models\Api\ApiKey;
use App\Models\Api\ApiRequestLog;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiRequestLog>
 */
class ApiRequestLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'api_key_id' => ApiKey::factory(),
            'request_id' => (string) Str::uuid(),
            'method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'endpoint' => '/api/v1/products',
            'status_code' => $this->faker->randomElement([200, 201, 400, 404, 429]),
            'duration_ms' => $this->faker->numberBetween(10, 500),
            'ip_address' => $this->faker->ipv4(),
            'requested_at' => now(),
        ];
    }
}
