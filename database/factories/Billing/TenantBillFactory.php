<?php

namespace Database\Factories\Billing;

use App\Domain\Enums\BillStatus;
use App\Models\Billing\TenantBill;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantBill>
 */
class TenantBillFactory extends Factory
{
    public function definition(): array
    {
        $transactionTotal = $this->faker->randomFloat(2, 1000, 100_000);
        $commission = round($transactionTotal * 0.02, 2);
        $apiFee = $this->faker->randomFloat(2, 0, 200);

        return [
            'tenant_id' => Tenant::factory(),
            'billing_period' => $this->faker->dateTimeThisYear()->format('Y-m'),
            'transaction_total' => $transactionTotal,
            'commission_amount' => $commission,
            'api_usage_fee' => $apiFee,
            'api_overage_fee' => 0,
            'total_receivable' => round($commission + $apiFee, 2),
            'merchant_reported_amount' => null,
            'difference_amount' => null,
            'status' => BillStatus::PendingSettlement,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
