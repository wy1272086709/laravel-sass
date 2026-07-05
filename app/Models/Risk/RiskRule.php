<?php

declare(strict_types=1);

namespace App\Models\Risk;

use App\Domain\Enums\RiskAlertType;
use App\Domain\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 风控规则（平台域，无 tenant_id）。SDD §8 内置 5 条规则。
 */
class RiskRule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'alert_type' => RiskAlertType::class,
            'risk_level' => RiskLevel::class,
            'threshold_config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
