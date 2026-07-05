<?php

namespace Database\Factories\Api;

use App\Domain\Enums\ApiKeyStatus;
use App\Domain\Enums\ApiPermission;
use App\Models\Api\ApiKey;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->word() . ' ERP',
            'app_key' => 'AK_' . $this->faker->unique()->numerify('########'),
            'app_secret' => Hash::make('secret'), // HASH 存储
            'permissions' => [ApiPermission::ProductQuery, ApiPermission::OrderManage],
            'status' => ApiKeyStatus::Enabled,
            'last_used_at' => null,
        ];
    }
}
