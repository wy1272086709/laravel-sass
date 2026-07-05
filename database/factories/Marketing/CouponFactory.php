<?php

namespace Database\Factories\Marketing;

use App\Domain\Enums\CouponStatus;
use App\Domain\Enums\CouponType;
use App\Models\Marketing\Coupon;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->word().' 券',
            'type' => CouponType::Discount,
            'status' => CouponStatus::Active,
            'discount_value' => $this->faker->numberBetween(50, 95), // 折扣百分比
            'min_amount' => $this->faker->randomFloat(2, 0, 500),
            'starts_at' => now(),
            'ends_at' => now()->addDays(7),
            'usage_limit' => $this->faker->numberBetween(50, 500),
            'used_count' => 0,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
