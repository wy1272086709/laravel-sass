<?php

namespace Database\Factories\Platform;

use App\Domain\Enums\PackageTier;
use App\Models\Platform\Package;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tier' => Arr::random(PackageTier::cases()),
            'name' => '套餐 '.Str::upper(Str::random(8)),
            'price_monthly' => random_int(9900, 99900) / 100,
            'api_quota_daily' => random_int(10_000, 1_000_000),
            'merchant_limit' => 1,
            'features' => ['dashboard' => true, 'api' => true],
            'is_active' => true,
        ];
    }
}
