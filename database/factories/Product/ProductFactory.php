<?php

namespace Database\Factories\Product;

use App\Domain\Enums\ProductStatus;
use App\Models\Product\Product;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_code' => 'G-'.$this->faker->unique()->numberBetween(10000, 99999),
            'name' => $this->faker->words(3, true),
            'cover_image' => null,
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'stock' => $this->faker->numberBetween(0, 500),
            'sales_count' => $this->faker->numberBetween(0, 1000),
            'specs' => ['颜色' => '默认'],
            'status' => ProductStatus::Listed,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
