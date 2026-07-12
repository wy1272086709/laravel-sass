<?php

namespace Database\Factories\Billing;

use App\Domain\Enums\ReconciliationStatus;
use App\Models\Billing\ReconciliationDiscrepancy;
use App\Models\Billing\TenantBill;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationDiscrepancy>
 */
class ReconciliationDiscrepancyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'tenant_bill_id' => TenantBill::factory(),
            'difference_amount' => $this->faker->randomFloat(2, 1, 1000),
            'status' => ReconciliationStatus::Unreconciled,
            'note' => $this->faker->sentence(),
        ];
    }
}
