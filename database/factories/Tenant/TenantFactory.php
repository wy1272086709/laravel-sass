<?php

namespace Database\Factories\Tenant;

use App\Domain\Enums\TenantStatus;
use App\Models\Platform\Package;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_code' => 'MHT-' . $this->faker->unique()->numberBetween(10000, 99999),
            'name' => $this->faker->company(),
            'contact_name' => $this->faker->name(),
            'contact_phone' => $this->faker->phoneNumber(),
            'package_id' => Package::factory(),
            'status' => TenantStatus::Enabled,
            'commission_rate' => 0.0200,
            'joined_at' => now(),
        ];
    }
}
