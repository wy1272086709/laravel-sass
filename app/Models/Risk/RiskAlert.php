<?php

declare(strict_types=1);

namespace App\Models\Risk;

use App\Domain\Enums\RiskAlertStatus;
use App\Domain\Enums\RiskAlertType;
use App\Domain\Enums\RiskLevel;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Order\Order;
use App\Models\Platform\PlatformUser;
use App\Models\Tenant\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 风控告警（租户域）。RuleEngine 命中规则后写入，运营处置。
 */
class RiskAlert extends Model
{
    use HasFactory, BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => RiskAlertType::class,
            'risk_level' => RiskLevel::class,
            'status' => RiskAlertStatus::class,
            'context' => 'array',
            'triggered_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<PlatformUser, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'handled_by');
    }
}
