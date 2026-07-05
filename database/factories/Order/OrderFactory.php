<?php

namespace Database\Factories\Order;

use App\Domain\Enums\OrderStatus;
use App\Models\Order\Order;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'order_no' => 'ORD'.$this->faker->unique()->numerify('############'),
            'buyer_name' => $this->faker->name(),
            'buyer_phone' => $this->faker->phoneNumber(),
            'total_amount' => $this->faker->randomFloat(2, 10, 5000),
            'status' => OrderStatus::PendingPayment,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
