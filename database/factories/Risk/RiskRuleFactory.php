<?php

namespace Database\Factories\Risk;

use App\Domain\Enums\RiskAlertType;
use App\Domain\Enums\RiskLevel;
use App\Models\Risk\RiskRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskRule>
 */
class RiskRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'rule_'.$this->faker->unique()->word(),
            'name' => $this->faker->words(3, true),
            'alert_type' => RiskAlertType::BrushOrder,
            'risk_level' => RiskLevel::Medium,
            'threshold_config' => [],
            'is_active' => true,
            'description' => $this->faker->sentence(),
        ];
    }
}
