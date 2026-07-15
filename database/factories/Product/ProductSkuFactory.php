<?php

namespace Database\Factories\Product;

use App\Models\Product\Product;
use App\Models\Product\ProductSku;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductSku> */
class ProductSkuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'sku_code' => 'SKU-'.$this->faker->unique()->numberBetween(10000, 99999),
            'specs' => ['颜色' => $this->faker->safeColorName()],
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'stock' => $this->faker->numberBetween(0, 500),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
        ]);
    }
}
