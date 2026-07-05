<?php

namespace Database\Factories\Platform;

use App\Domain\Enums\PackageTier;
use App\Models\Platform\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tier' => $this->faker->randomElement(PackageTier::cases()),
            'name' => $this->faker->unique()->word() . ' 套餐',
            'price_monthly' => $this->faker->randomFloat(2, 99, 999),
            'api_quota_daily' => $this->faker->numberBetween(10_000, 1_000_000),
            'merchant_limit' => 1,
            'features' => ['dashboard' => true, 'api' => true],
            'is_active' => true,
        ];
    }
}
