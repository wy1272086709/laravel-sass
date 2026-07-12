<?php

namespace Database\Factories\Risk;

use App\Domain\Enums\RiskAlertStatus;
use App\Domain\Enums\RiskAlertType;
use App\Domain\Enums\RiskLevel;
use App\Models\Risk\RiskAlert;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskAlert>
 */
class RiskAlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => RiskAlertType::BrushOrder,
            'risk_level' => RiskLevel::Medium,
            'status' => RiskAlertStatus::Pending,
            'context' => ['reason' => 'factory'],
            'triggered_at' => now(),
        ];
    }
}
